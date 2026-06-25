<?php

declare(strict_types=1);

use App\Actions\Admin\Advertising\CreateAdCreativeAction;
use App\Actions\Admin\Advertising\DeleteAdCreativeAction;
use App\Actions\Admin\Advertising\ForceDeleteAdCreativeAction;
use App\Actions\Admin\Advertising\RestoreAdCreativeAction;
use App\Actions\Admin\Advertising\UpdateAdCreativeAction;
use App\Enums\AdCreativeType;
use App\Http\Requests\Admin\Advertising\StoreAdCreativeRequest;
use App\Models\AdCampaign;
use App\Models\AdCreative;
use App\Models\AdPlacement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

it('creates an html creative with sanitized html_code and no media', function (): void {
    $campaign = AdCampaign::factory()->create();

    $res = (new CreateAdCreativeAction)->handle([
        'ad_campaign_id' => $campaign->id,
        'type' => 'html',
        'title' => 'Promo',
        'html_code' => '<div onclick="x()">Hi<script>alert(1)</script></div>',
    ]);

    expect($res->getStatusCode())->toBe(201);

    $creative = AdCreative::where('title', 'Promo')->first();

    expect($creative->type)->toBe(AdCreativeType::Html)
        ->and($creative->html_code)->not->toContain('<script')
        ->and($creative->html_code)->not->toContain('onclick')
        ->and($creative->html_code)->toContain('Hi')
        ->and($creative->media_asset_id)->toBeNull();
});

it('nulls html_code on an image creative (cross-field hygiene)', function (): void {
    $campaign = AdCampaign::factory()->create();

    (new CreateAdCreativeAction)->handle([
        'ad_campaign_id' => $campaign->id,
        'type' => 'image',
        'title' => 'Banner',
        'html_code' => '<div>leftover</div>',
    ]);

    $creative = AdCreative::where('title', 'Banner')->first();

    expect($creative->type)->toBe(AdCreativeType::Image)
        ->and($creative->html_code)->toBeNull();
});

it('rejects a video creative as a not-enabled feature (not invalid)', function (): void {
    $validator = Validator::make(
        ['ad_campaign_id' => 1, 'type' => 'video', 'title' => 'V'],
        (new StoreAdCreativeRequest)->rules()
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('type'))->toBe(__('ads.creative.video_not_enabled'));
});

it('updates a creative and re-sanitizes provided html', function (): void {
    $creative = AdCreative::factory()->html()->create(['title' => 'Old']);

    $res = (new UpdateAdCreativeAction)->handle($creative, [
        'title' => 'New',
        'html_code' => '<p>clean<script>bad()</script></p>',
    ]);

    expect($res->getStatusCode())->toBe(200)
        ->and($creative->fresh()->title)->toBe('New')
        ->and($creative->fresh()->html_code)->not->toContain('<script')
        ->and($creative->fresh()->html_code)->toContain('clean');
});

it('soft-deletes then restores a creative', function (): void {
    $creative = AdCreative::factory()->create();

    (new DeleteAdCreativeAction)->handle($creative);

    expect(AdCreative::whereKey($creative->id)->exists())->toBeFalse()
        ->and(AdCreative::withTrashed()->whereKey($creative->id)->exists())->toBeTrue();

    (new RestoreAdCreativeAction)->handle($creative);

    expect(AdCreative::whereKey($creative->id)->exists())->toBeTrue();
});

it('force-deletes a creative and cascades to its placements', function (): void {
    $creative = AdCreative::factory()->create();
    $placement = AdPlacement::factory()->create(['ad_creative_id' => $creative->id]);

    $res = (new ForceDeleteAdCreativeAction)->handle($creative);

    expect($res->getStatusCode())->toBe(200)
        ->and(AdCreative::withTrashed()->whereKey($creative->id)->exists())->toBeFalse()
        ->and(AdPlacement::whereKey($placement->id)->exists())->toBeFalse();
});

it('writes an activity-log entry when a creative is created', function (): void {
    $campaign = AdCampaign::factory()->create();

    (new CreateAdCreativeAction)->handle([
        'ad_campaign_id' => $campaign->id,
        'type' => 'image',
        'title' => 'Audited',
    ]);

    expect(Activity::where('log_name', 'ad_creative')->where('event', 'created')->exists())->toBeTrue();
});

it('sanitizes html_code at the model boundary on a direct (non-Action) write', function (): void {
    // مسار كتابة لا يمرّ بالـ Action (factory) — يجب أن يُنقَّى عبر مُحوّل النموذج (V8).
    $creative = AdCreative::factory()->html()->create([
        'html_code' => '<p>safe<script>steal()</script></p><a href="javascript:evil()">x</a>',
    ]);

    $stored = (string) $creative->fresh()->html_code;

    expect($stored)->not->toContain('<script')
        ->and($stored)->not->toContain('javascript:')
        ->and($stored)->toContain('safe');
});

it('passes a null html_code through the model mutator unchanged', function (): void {
    $creative = AdCreative::factory()->create(['type' => 'image', 'html_code' => null]);

    expect($creative->fresh()->html_code)->toBeNull();
});
