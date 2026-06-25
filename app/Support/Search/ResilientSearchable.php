<?php

declare(strict_types=1);

namespace App\Support\Search;

use Closure;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Laravel\Scout\Searchable;
use Meilisearch\Exceptions\CommunicationException;
use Meilisearch\Exceptions\TimeOutException;
use Throwable;

/**
 * Scout قابل للبحث لكنه «فاشل-آمن» — بنطاق ضيّق: تعطّل البنية التحتية للبحث
 * (محرّك غير متاح/مهلة اتصال) يجب ألّا يُسقِط عملية تحريرية. نلتقط **فقط** أعطال
 * النقل/الاتصال (CommunicationException / TimeOutException / ConnectException،
 * وعبر سلسلة previous)، ونُسجّلها تحذيراً. أمّا الأخطاء الأخرى — أخطاء برمجية
 * (TypeError/Error)، أو ApiException/InvalidArgument/JsonEncoding (سوء إعداد أو
 * عطب في toSearchableArray) — فتتسرّب كما هي حتى لا نُخفي عللاً حقيقية.
 *
 * يغلّف نقطتي مزامنة Scout (queueMakeSearchable / queueRemoveFromSearch) اللتين
 * تُستدعيان من مراقب النموذج عند الحفظ/الحذف — يغطّي المسار المتزامن والمُطابور.
 */
trait ResilientSearchable
{
    use Searchable {
        queueMakeSearchable as protected baseQueueMakeSearchable;
        queueRemoveFromSearch as protected baseQueueRemoveFromSearch;
    }

    /**
     * أنواع أعطال النقل/الاتصال القابلة للابتلاع (بالاسم — instanceof آمن حتى لو
     * لم تُثبَّت الحزمة، إذ يُعيد false دون خطأ). محصورة عمداً في الاتصال/المهلة.
     *
     * @var array<int,class-string>
     */
    private static array $searchTransportFailures = [
        CommunicationException::class,
        TimeOutException::class,
        ConnectException::class,
    ];

    /** @param  Collection<int,Model>  $models */
    public function queueMakeSearchable($models): void
    {
        $this->guardSearchSync(fn () => $this->baseQueueMakeSearchable($models));
    }

    /** @param  Collection<int,Model>  $models */
    public function queueRemoveFromSearch($models): void
    {
        $this->guardSearchSync(fn () => $this->baseQueueRemoveFromSearch($models));
    }

    private function guardSearchSync(Closure $sync): void
    {
        try {
            $sync();
        } catch (Throwable $e) {
            // الأخطاء غير المتعلّقة بالنقل/الاتصال (علل تطبيقية حقيقية) تتسرّب.
            if (! $this->isSearchTransportFailure($e)) {
                throw $e;
            }

            Log::warning('search.sync_failed', [
                'model' => static::class,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** هل العطل (أو أحد أسبابه في السلسلة) عطل نقل/اتصال بمحرّك البحث؟ */
    private function isSearchTransportFailure(?Throwable $e): bool
    {
        while ($e !== null) {
            foreach (self::$searchTransportFailures as $type) {
                if ($e instanceof $type) {
                    return true;
                }
            }
            $e = $e->getPrevious();
        }

        return false;
    }
}
