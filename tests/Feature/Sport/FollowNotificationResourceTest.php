<?php

declare(strict_types=1);

use App\Http\Resources\Public\NotificationResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('ar');
});

/**
 * يُمرّر حمولة إشعارٍ عبر NotificationResource ويعيد المصفوفة المعروضة.
 *
 * @param  array<string,mixed>  $data
 * @return array<string,mixed>
 */
function followNotifArray(array $data): array
{
    $user = User::factory()->create();
    $notification = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\FollowTest',
        'data' => $data,
        'read_at' => null,
    ]);

    return (new NotificationResource($notification))->toArray(request());
}

it('renders a match reminder notification (title + message + match url)', function (): void {
    $r = followNotifArray([
        'kind' => 'match_reminder', 'game_id' => 4627876, 'competition_id' => 5930,
        'home' => 'فرنسا', 'away' => 'السنغال',
    ]);

    expect($r['title'])->toBe('تذكير مباراة')
        ->and($r['message'])->toContain('فرنسا')->toContain('السنغال')
        ->and($r['url'])->toBe('/sport/match/4627876')
        ->and($r['message'])->not->toBeNull()
        ->and($r['url'])->not->toBeNull();
});

it('renders a goal event notification', function (): void {
    $r = followNotifArray([
        'kind' => 'match_event', 'event_type_id' => 1, 'game_id' => 4627876,
        'player' => 'العمري', 'minute' => "41'", 'home' => 'فرنسا', 'away' => 'السنغال',
    ]);

    expect($r['title'])->toBe('هدف')
        ->and($r['message'])->toContain('هدف')->toContain('العمري')
        ->and($r['url'])->toBe('/sport/match/4627876');
});

it('renders a yellow card event notification', function (): void {
    $r = followNotifArray([
        'kind' => 'match_event', 'event_type_id' => 2, 'game_id' => 4627876, 'player' => 'العمري', 'minute' => "44'",
    ]);

    expect($r['title'])->toBe('بطاقة صفراء')->and($r['message'])->toContain('صفراء');
});

it('renders a red card event notification', function (): void {
    $r = followNotifArray([
        'kind' => 'match_event', 'event_type_id' => 3, 'game_id' => 4627876, 'player' => 'العمري', 'minute' => "80'",
    ]);

    expect($r['title'])->toBe('بطاقة حمراء')->and($r['message'])->toContain('حمراء');
});

it('never returns a null url — falls back to competition then /sport', function (): void {
    // بلا game_id لكن ببطولة ⇒ رابط البطولة.
    $comp = followNotifArray(['kind' => 'match_event', 'event_type_id' => 1, 'competition_id' => 5930]);
    expect($comp['url'])->toBe('/sport/competition/5930')->and($comp['url'])->not->toBeNull();

    // بلا أيّ معرّف ⇒ /sport؛ والرسالة لا تكون null أبداً.
    $none = followNotifArray(['kind' => 'match_reminder']);
    expect($none['url'])->toBe('/sport')
        ->and($none['message'])->not->toBeNull()
        ->and($none['title'])->not->toBeNull();
});
