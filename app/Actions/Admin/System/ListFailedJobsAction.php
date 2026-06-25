<?php

declare(strict_types=1);

namespace App\Actions\Admin\System;

use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * يسرد المهام الفاشلة من مزوّد queue.failer (database-uuids) مع بحث وترقيم.
 *
 * يقرأ مباشرةً من جدول failed_jobs عبر الواجهة الرسمية (لا وصول خام). لا تشغيل
 * مهام هنا — قراءة فقط. الترقيم في الذاكرة مقبول: المهام الفاشلة يُفترَض أن تكون
 * قليلة (تراكمها هو الإنذار نفسه).
 */
class ListFailedJobsAction
{
    public function handle(array $filters): JsonResponse
    {
        $jobs = $this->all()->map(fn (object $job): array => $this->transform($job));

        if (($q = trim((string) ($filters['q'] ?? ''))) !== '') {
            $needle = mb_strtolower($q);
            $jobs = $jobs->filter(function (array $r) use ($needle): bool {
                $hay = mb_strtolower($r['name'].' '.$r['queue'].' '.$r['connection'].' '.$r['exception'].' '.$r['id']);

                return str_contains($hay, $needle);
            });
        }

        $jobs = $jobs->values();
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);
        $page = max((int) ($filters['page'] ?? 1), 1);
        $total = $jobs->count();

        return ApiResponse::success(data: [
            'data' => $jobs->forPage($page, $perPage)->values()->all(),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
            ],
        ]);
    }

    /** @return Collection<int,object> */
    private function all(): Collection
    {
        /** @var array<int,object> $rows */
        $rows = app('queue.failer')->all();

        return collect($rows);
    }

    /** @return array<string,mixed> */
    private function transform(object $job): array
    {
        $payload = json_decode((string) ($job->payload ?? ''), true);
        $payload = is_array($payload) ? $payload : [];

        return [
            'id' => $job->id, // UUID (database-uuids driver)
            'connection' => $job->connection ?? null,
            'queue' => $job->queue ?? null,
            'name' => $payload['displayName'] ?? ($payload['job'] ?? 'unknown'),
            'max_tries' => $payload['maxTries'] ?? null,
            'exception' => $this->summarizeException((string) ($job->exception ?? '')),
            'failed_at' => $job->failed_at ?? null,
        ];
    }

    /** أوّل سطر من أثر الاستثناء، مقتطعاً — يكفي للتشخيص دون تضخيم الحمولة. */
    private function summarizeException(string $exception): string
    {
        $firstLine = trim(strtok($exception, "\n") ?: '');

        return mb_substr($firstLine, 0, 300);
    }
}
