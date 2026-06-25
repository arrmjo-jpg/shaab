<?php

declare(strict_types=1);

namespace App\Health\Checks;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * حارس إنتاج: يفرض أن الطابور والكاش على Redis في بيئة الإنتاج.
 *
 * بنية AlphaCMS تعتمد دلالات Redis فعلياً: عمّال الطوابير (ترميز/مرآة/مُجدوِل)،
 * وسوم الكاش (Cache::tags لكل المحتوى)، وكاش التفاعل. السائق database لا يدعم
 * الوسوم (يُلقي استثناءً) ويتنافس مع جداول المحتوى الساخنة — غير مقبول للإنتاج.
 *
 * environment-safe: الفرض في الإنتاج فقط. في local/dev/testing يمرّ الفحص (ok)
 * كي تبقى البيئة قابلة للاستخدام بسائق database/array دون إزعاج.
 */
class RedisProductionCheck extends Check
{
    public function run(): Result
    {
        $queue = (string) config('queue.default');
        $cache = (string) config('cache.default');

        $meta = [
            'environment' => app()->environment(),
            'queue.default' => $queue,
            'cache.default' => $cache,
        ];

        // الفرض في الإنتاج فقط — local/dev/testing يبقى قابلاً للاستخدام.
        if (! app()->environment('production')) {
            return Result::make()->meta($meta)
                ->ok("Redis enforcement applies in production only (env: {$queue}/{$cache}).");
        }

        $problems = [];
        if ($queue !== 'redis') {
            $problems[] = "QUEUE_CONNECTION is [{$queue}], must be [redis]";
        }
        if ($cache !== 'redis') {
            $problems[] = "CACHE_STORE is [{$cache}], must be [redis]";
        }

        if ($problems !== []) {
            return Result::make()->meta($meta + ['problems' => $problems])
                ->failed(
                    'Production requires Redis for queue and cache: '.implode('; ', $problems)
                    .'. Set QUEUE_CONNECTION=redis and CACHE_STORE=redis.'
                );
        }

        return Result::make()->meta($meta)->ok('Queue and cache are on Redis (production-ready).');
    }
}
