<?php

declare(strict_types=1);

use App\Models\EngagementCounter;
use App\Support\Engagement\EngagementActor;
use App\Support\Engagement\EngagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * يثبّت تحسينات Phase 6 لأكثر مسارات الكتابة سخونة (احتساب المشاهدة):
 * المسار الساخن (الصفّ موجود) = استعلام UPDATE واحد فقط.
 */
it('records a repeat view in a single DB query (hot path)', function (): void {
    EngagementCounter::create([
        'engageable_type' => 'App\\Models\\Reel',
        'engageable_id' => 1,
        'views' => 5,
    ]);

    DB::enableQueryLog();
    app(EngagementService::class)->recordViewFor('App\\Models\\Reel', 1, EngagementActor::guest('visitor-1'));

    expect(count(DB::getQueryLog()))->toBe(1); // increment فقط — لا SELECT مسبق
    expect((int) EngagementCounter::first()->views)->toBe(6);
});

it('creates the counter on the first view then increments to one', function (): void {
    app(EngagementService::class)->recordViewFor('App\\Models\\Reel', 9, EngagementActor::guest('v'));

    expect((int) EngagementCounter::where('engageable_id', 9)->value('views'))->toBe(1);
});

it('dedups a repeat view from the same actor (no extra increment)', function (): void {
    $service = app(EngagementService::class);
    $actor = EngagementActor::guest('same-visitor');

    $service->recordViewFor('App\\Models\\Reel', 7, $actor);
    $service->recordViewFor('App\\Models\\Reel', 7, $actor); // ضمن نافذة منع التكرار

    expect((int) EngagementCounter::where('engageable_id', 7)->value('views'))->toBe(1);
});
