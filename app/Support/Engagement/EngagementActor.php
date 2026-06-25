<?php

declare(strict_types=1);

namespace App\Support\Engagement;

use Illuminate\Http\Request;

/**
 * هوية الفاعل الموحّدة (هجين): مستخدم مُصادَق أو زائر مُعرَّف ببصمة.
 *
 * - مُصادَق  → key = "u{id}"
 * - زائر     → key = "f{hash}" (تجزئة بصمة العميل، أو IP+UA كحدٍّ أدنى)
 */
final class EngagementActor
{
    private function __construct(
        public readonly ?int $userId,
        public readonly ?string $fingerprint,
        /** هل الفاعل زاحف/بوت (من User-Agent)؟ لمنع احتساب مشاهداته. */
        public readonly bool $isBot = false,
    ) {}

    public static function user(int $userId): self
    {
        return new self($userId, null);
    }

    public static function guest(string $fingerprint): self
    {
        return new self(null, substr(hash('sha256', $fingerprint), 0, 64));
    }

    /** يشتقّ الفاعل من الطلب: المستخدم إن وُجد، وإلا بصمة من رأس مخصّص أو IP+UA. */
    public static function fromRequest(Request $request): self
    {
        $isBot = BotSignature::isBot($request->userAgent());

        // النقاط العامّة بلا auth:sanctum والحارس الافتراضيّ web/session؛ نحلّ المستخدم عبر حارس
        // sanctum ليُقرأ الـBearer الذي يمرّره الـBFF (وإلا سقط كلّ تفاعل مُصادَق إلى بصمة زائر).
        $user = $request->user('sanctum');
        if ($user !== null) {
            return new self((int) $user->id, null, $isBot);
        }

        $client = (string) $request->header('X-Client-Id', '');
        $seed = $client !== ''
            ? $client
            : (string) $request->ip().'|'.(string) $request->userAgent();

        return new self(null, substr(hash('sha256', $seed), 0, 64), $isBot);
    }

    public function key(): string
    {
        return $this->userId !== null ? 'u'.$this->userId : 'f'.$this->fingerprint;
    }
}
