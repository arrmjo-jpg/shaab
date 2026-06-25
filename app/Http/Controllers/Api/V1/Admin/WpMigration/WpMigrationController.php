<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\WpMigration;

use App\Actions\Admin\WpMigration\ApproveMigrationRunAction;
use App\Actions\Admin\WpMigration\AuditMigrationRunAction;
use App\Actions\Admin\WpMigration\GeneratePreviewAction;
use App\Actions\Admin\WpMigration\ImportTaxonomyAction;
use App\Actions\Admin\WpMigration\PauseMigrationAction;
use App\Actions\Admin\WpMigration\QuickIncrementalImportAction;
use App\Actions\Admin\WpMigration\ResumeMigrationAction;
use App\Actions\Admin\WpMigration\RetryMigrationItemsAction;
use App\Actions\Admin\WpMigration\SaveCategoryMapsAction;
use App\Actions\Admin\WpMigration\StartMigrationAction;
use App\Actions\Admin\WpMigration\StopMigrationAction;
use App\Actions\Admin\WpMigration\StoreMigrationRunAction;
use App\Actions\Admin\WpMigration\TestWpConnectionAction;
use App\Enums\MigrationItemStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WpMigration\ApproveMigrationRunRequest;
use App\Http\Requests\Admin\WpMigration\RetryMigrationItemsRequest;
use App\Http\Requests\Admin\WpMigration\SaveCategoryMapsRequest;
use App\Http\Requests\Admin\WpMigration\StoreMigrationRunRequest;
use App\Http\Requests\Admin\WpMigration\TestWpConnectionRequest;
use App\Http\Resources\Admin\WpMigration\MigrationItemResource;
use App\Http\Resources\Admin\WpMigration\MigrationRunResource;
use App\Models\Category;
use App\Models\MigrationRun;
use App\Support\Responses\ApiResponse;
use App\Support\WpMigration\MigrationStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WpMigrationController extends Controller
{
    public function index(): JsonResponse
    {
        $runs = MigrationRun::query()->latest()->get();

        return ApiResponse::success(__('wp_migration.run.listed'), MigrationRunResource::collection($runs));
    }

    public function show(MigrationRun $run): JsonResponse
    {
        return ApiResponse::success(__('wp_migration.run.shown'), new MigrationRunResource($run));
    }

    public function testConnection(TestWpConnectionRequest $request): JsonResponse
    {
        return (new TestWpConnectionAction)->handle($request->validated());
    }

    public function store(StoreMigrationRunRequest $request): JsonResponse
    {
        return (new StoreMigrationRunAction)->handle($request->validated());
    }

    public function audit(MigrationRun $run): JsonResponse
    {
        return (new AuditMigrationRunAction)->handle($run);
    }

    /** تصنيفات المصدر مدموجة مع التنسيب المحفوظ (Step 3–4). */
    public function categories(MigrationRun $run): JsonResponse
    {
        /** @var array<int,array<string,mixed>> $source */
        $source = data_get($run->source_facts, 'categories.items', []);
        $maps = $run->categoryMaps()->get()->keyBy('wp_term_id');

        $items = collect($source)->map(function (array $c) use ($maps): array {
            $map = $maps->get($c['term_id']);

            return [
                'term_id' => $c['term_id'],
                'name' => $c['name'],
                'slug' => $c['slug'],
                'parent' => $c['parent'],
                'count' => $c['count'],
                'total_count' => $c['total_count'] ?? $c['count'],
                'mode' => $map?->mode->value ?? 'exclude',
                'disposition' => $map?->disposition->value ?? 'exclude',
                'target_category_id' => $map?->target_category_id,
                'created_category_id' => $map?->created_category_id,
            ];
        })->all();

        return ApiResponse::success(__('wp_migration.run.shown'), ['items' => $items]);
    }

    /** مجمّعات تصنيفات AlphaCMS الهدف، مُقسَّمة بالنطاق (أخبار/مقالات) ومُطابقة اللغة. */
    public function targetCategories(MigrationRun $run): JsonResponse
    {
        $locale = (string) (data_get($run->source_facts, 'site.language') ?: 'ar');

        $pool = fn (array $scopes): array => Category::query()
            ->where('status', 'active')
            ->where('locale', $locale)
            ->whereIn('scope', $scopes)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'scope', 'parent_id'])
            ->map(fn (Category $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'scope' => $c->scope->value,
                'parent_id' => $c->parent_id,
            ])->all();

        return ApiResponse::success(__('wp_migration.run.shown'), [
            'locale' => $locale,
            'news' => $pool(['news', 'both']),
            'articles' => $pool(['opinion', 'both']),
        ]);
    }

    public function saveCategoryMaps(SaveCategoryMapsRequest $request, MigrationRun $run): JsonResponse
    {
        return (new SaveCategoryMapsAction)->handle($run, $request->validated()['maps']);
    }

    /** Step 4.5: استيراد التصنيفات (إنشاء تصنيفات AlphaCMS من المصدر) — بعد التنسيب، قبل المعاينة. */
    public function importTaxonomy(MigrationRun $run): JsonResponse
    {
        return (new ImportTaxonomyAction)->handle($run);
    }

    public function preview(MigrationRun $run): JsonResponse
    {
        return (new GeneratePreviewAction)->handle($run);
    }

    public function approve(ApproveMigrationRunRequest $request, MigrationRun $run): JsonResponse
    {
        return (new ApproveMigrationRunAction)->handle($run, $request->validated()['conflict_policy']);
    }

    // Step 6: تنفيذ مُنسَّق (طابور) — بدء/إيقاف مؤقّت/استئناف/إيقاف آمن.
    public function start(Request $request, MigrationRun $run): JsonResponse
    {
        return (new StartMigrationAction)->handle($run, $request->boolean('incremental'));
    }

    // ⚠️ TEMPORARY FEATURE
    // Quick Incremental Import
    // Remove before Production release
    // TODO(production): احذفه أو عطّله (WP_MIGRATION_QUICK_INCREMENTAL=false) — التحقّق خلفيّ في الإجراء.
    public function quickIncremental(MigrationRun $run): JsonResponse
    {
        return (new QuickIncrementalImportAction)->handle($run);
    }

    public function pause(MigrationRun $run): JsonResponse
    {
        return (new PauseMigrationAction)->handle($run);
    }

    public function resume(MigrationRun $run): JsonResponse
    {
        return (new ResumeMigrationAction)->handle($run);
    }

    public function stop(MigrationRun $run): JsonResponse
    {
        return (new StopMigrationAction)->handle($run);
    }

    // Steps 7–9: مراقبة حيّة + تنقيب في الفشل + إعادة محاولة + تقرير ختام.
    public function stats(MigrationRun $run): JsonResponse
    {
        return ApiResponse::success(__('wp_migration.run.shown'), MigrationStats::for($run)->build());
    }

    public function report(MigrationRun $run): JsonResponse
    {
        return ApiResponse::success(__('wp_migration.run.shown'), MigrationStats::for($run)->report());
    }

    /** دفتر العناصر مع ترشيح بالحالة (failed/partial/skipped/processing) + ترقيم. */
    public function items(Request $request, MigrationRun $run): JsonResponse
    {
        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) $request->integer('per_page', $default), $max));

        $query = $run->items()->orderByDesc('updated_at')->orderByDesc('id');

        $status = (string) $request->query('status', '');
        if (in_array($status, MigrationItemStatus::values(), true)) {
            $query->where('status', $status);
        }

        $items = $query->paginate($perPage)->appends($request->query());

        return ApiResponse::success(
            message: __('wp_migration.run.shown'),
            data: MigrationItemResource::collection($items)->resolve(),
            meta: [
                'pagination' => [
                    'total' => $items->total(),
                    'count' => $items->count(),
                    'per_page' => $items->perPage(),
                    'current_page' => $items->currentPage(),
                    'total_pages' => $items->lastPage(),
                ],
            ],
        );
    }

    public function retry(RetryMigrationItemsRequest $request, MigrationRun $run): JsonResponse
    {
        $data = $request->validated();

        return (new RetryMigrationItemsAction)->handle($run, $data['mode'], $data['ids'] ?? []);
    }
}
