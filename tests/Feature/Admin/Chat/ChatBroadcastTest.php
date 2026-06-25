<?php

declare(strict_types=1);

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;

uses(RefreshDatabase::class);

function bcConversation(User $owner): Conversation
{
    $c = Conversation::create(['type' => 'group', 'title' => 'فريق', 'created_by' => $owner->id]);
    $c->participants()->create(['user_id' => $owner->id]);

    return $c;
}

// ─── Event contract ──────────────────────────────────────────────────────────

it('broadcasts MessageSent on the conversation private channel with an id-keyed payload', function (): void {
    $u = User::factory()->create();
    $conv = bcConversation($u);
    $msg = $conv->messages()->create(['user_id' => $u->id, 'body' => 'مرحبا']);

    $event = new MessageSent($msg);
    $channels = $event->broadcastOn();

    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[0]->name)->toBe('private-chat.conversation.'.$conv->id);
    expect($event->broadcastAs())->toBe('message.sent');

    $payload = $event->broadcastWith();
    expect($payload['id'])->toBe($msg->id);          // مفتاح موحّد للدمج/الترتيب
    expect($payload['body'])->toBe('مرحبا');
    expect($payload['mine'])->toBeFalse();           // toOthers → كل المستلمين «آخرون»
});

it('broadcasts only after the DB commit (ShouldBroadcast + $afterCommit)', function (): void {
    $u = User::factory()->create();
    $conv = bcConversation($u);
    $msg = $conv->messages()->create(['user_id' => $u->id, 'body' => 'x']);

    $event = new MessageSent($msg);
    expect($event)->toBeInstanceOf(ShouldBroadcast::class);
    expect($event->afterCommit)->toBeTrue();
});

// ─── Channel authorization (membership only — لا مجرّد auth) ─────────────────

it('authorizes the conversation channel only for a participant', function (): void {
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $conv = bcConversation($member);

    // نفس منطق التفويض في routes/channels.php (Conversation::hasParticipant).
    expect($conv->hasParticipant($member->id))->toBeTrue();
    expect($conv->hasParticipant($outsider->id))->toBeFalse();
});

// ─── /broadcasting/auth: الحارس Sanctum (Bearer) لا web ──────────────────────
// تحت BROADCAST_CONNECTION=null يتجاوز NullBroadcaster كل التفويض (200 دائماً)، لذا
// 200/403 تُختبَران ببثّ reverb حقيقي؛ أمّا الحارس نفسه فيُؤكَّد مباشرةً + حالة 401.

it('guards /broadcasting/auth with Sanctum middleware, not web/session', function (): void {
    $route = collect(app('router')->getRoutes())
        ->first(fn ($r) => $r->uri() === 'broadcasting/auth');

    expect($route)->not->toBeNull();
    expect($route->gatherMiddleware())->toContain('auth:sanctum');
    expect($route->gatherMiddleware())->not->toContain('web');
});

it('rejects /broadcasting/auth without a token (401)', function (): void {
    $u = User::factory()->create();
    $conv = bcConversation($u);

    $this->postJson('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-chat.conversation.'.$conv->id,
    ])->assertUnauthorized();
});

// التفويض الفعليّ: نستدعي الـ closure المسجّلة في routes/channels.php مباشرةً (لا نسخة
// منطق). E2E عبر reverb غير حتميّ هنا لأن القنوات تُسجَّل على المُذيع الافتراضي وقت
// الإقلاع (null في phpunit)، فتبديل الإعداد لاحقاً يعطي مُذيعاً بلا قنوات. هذا الاختبار
// يضمن أن القناة مسجّلة فعلاً وأن الإغلاق يَصِل hasParticipant بشكل صحيح.

function chatChannelCallback(): Closure
{
    $driver = Broadcast::connection();
    $ref = new ReflectionClass($driver);
    $prop = $ref->getProperty('channels');
    $prop->setAccessible(true);
    $channels = $prop->getValue($driver);

    expect($channels)->toHaveKey('chat.conversation.{conversation}');

    return $channels['chat.conversation.{conversation}'];
}

it('registers the conversation channel and grants a participant', function (): void {
    $u = User::factory()->create();
    $conv = bcConversation($u);

    $callback = chatChannelCallback();

    expect($callback($u, (int) $conv->id))->toBeTrue();
});

it('the registered conversation channel denies a non-participant', function (): void {
    $member = User::factory()->create();
    $conv = bcConversation($member);
    $outsider = User::factory()->create();

    $callback = chatChannelCallback();

    expect($callback($outsider, (int) $conv->id))->toBeFalse();
    expect($callback($outsider, 999999))->toBeFalse(); // محادثة غير موجودة
});
