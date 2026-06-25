<?php

declare(strict_types=1);

namespace App\Support\Ai\Providers;

use App\Contracts\Ai\AiProvider;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * مزوّد مركّب (failover) — يلفّ قائمة مرتّبة من المزوّدين: الأساسي أولاً ثم
 * الاحتياطي. يطبّق نفس عقد AiProvider بلا تغيير، فلا يعرف المنطق التحريري
 * (AiEditorialService) شيئاً عن وجود تجاوز للفشل.
 *
 * عند فشل المزوّد الأساسي لأي سبب (مهلة/404/429/مصادقة/انقطاع) يُسجَّل الفشل
 * ويُجرَّب التالي تلقائياً. لا يفشل أمام المستخدم إلّا إذا فشل كل المزوّدين.
 */
final class FailoverAiProvider implements AiProvider
{
    /** اسم آخر مزوّد نجح فعلياً — للتسجيل الدقيق للاستخدام. */
    private ?string $lastUsed = null;

    /** @param  array<int,AiProvider>  $providers  مرتّبة: الأساسي أولاً */
    public function __construct(private readonly array $providers) {}

    public function chat(array $messages, array $options = []): string
    {
        $chain = array_values(array_filter(
            $this->providers,
            static fn (AiProvider $p): bool => $p->configured(),
        ));

        if ($chain === []) {
            throw new RuntimeException('ai_no_provider_configured');
        }

        $primaryName = $chain[0]->name();
        $lastError = null;

        foreach ($chain as $index => $provider) {
            try {
                $result = $provider->chat($messages, $options);
                $this->lastUsed = $provider->name();

                if ($index > 0) {
                    Log::warning('AI failover: fallback provider used', [
                        'primary' => $primaryName,
                        'fallback' => $provider->name(),
                    ]);
                }

                return $result;
            } catch (Throwable $e) {
                $lastError = $e;
                Log::warning('AI provider failed; attempting next', [
                    'provider' => $provider->name(),
                    'is_primary' => $index === 0,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // كل المزوّدين المُهيّئين فشلوا — أعِد آخر خطأ ليُترجَم إلى 503 لطيفة.
        throw $lastError ?? new RuntimeException('ai_all_providers_failed');
    }

    public function configured(): bool
    {
        foreach ($this->providers as $provider) {
            if ($provider->configured()) {
                return true;
            }
        }

        return false;
    }

    /**
     * اسم المزوّد المُستخدَم فعلياً (بعد أيّ تجاوز للفشل)، أو الأساسي المُهيّأ
     * إن لم يُجرَ نداء بعد — يُغذّي تسجيل الاستخدام بالقيمة الصحيحة.
     */
    public function name(): string
    {
        if ($this->lastUsed !== null) {
            return $this->lastUsed;
        }

        foreach ($this->providers as $provider) {
            if ($provider->configured()) {
                return $provider->name();
            }
        }

        return 'none';
    }
}
