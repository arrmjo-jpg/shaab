<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\MediaAsset;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function chatAdmin(): array
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return [$u, $u->createToken('admin-token', ['admin'])->plainTextToken];
}

/**
 * طلب مُصادَق بالـ token مع إعادة ضبط الحارس أولاً — يُجبِر middleware الـ sanctum على
 * إعادة الحلّ من ترويسة الطلب الحالية. ضروريّ عند تبديل الفاعل عبر طلبات نفس الاختبار
 * (Sanctum يُذاكِر المستخدم على نسخة الحارس — أثر اختباريّ لا إنتاجيّ؛ كل طلب إنتاجيّ
 * عملية مستقلّة). يُعيد نسخة الاختبار للسَّلسَلة.
 */
function asUser(string $token)
{
    app('auth')->forgetGuards();

    return test()->withToken($token);
}

function plainImage(array $attrs = []): MediaAsset
{
    return MediaAsset::create(array_merge([
        'uuid' => (string) Str::uuid(),
        'kind' => 'file', 'disk' => 'public', 'path' => 'm/o.jpg',
        'filename' => 'o.jpg', 'original_name' => 'o.jpg', 'mime_type' => 'image/jpeg',
        'extension' => 'jpg', 'size' => 100, 'visibility' => 'public',
    ], $attrs));
}

// ─── General room ────────────────────────────────────────────────────────────

it('auto-provisions the general room for any admin on listing', function (): void {
    [, $token] = chatAdmin();

    $res = $this->withToken($token)->getJson('/api/v1/admin/chat/conversations')->assertOk();

    $types = collect($res->json('data'))->pluck('type')->all();
    expect($types)->toContain('general');
});

// ─── Direct conversations + dedup ───────────────────────────────────────────

it('creates a direct conversation and dedups on a second attempt', function (): void {
    [$me, $token] = chatAdmin();
    $other = User::factory()->create();

    $a = $this->withToken($token)->postJson('/api/v1/admin/chat/conversations', [
        'type' => 'direct', 'user_ids' => [$other->id],
    ])->assertCreated();

    $b = $this->withToken($token)->postJson('/api/v1/admin/chat/conversations', [
        'type' => 'direct', 'user_ids' => [$other->id],
    ])->assertOk();

    expect($b->json('data.id'))->toBe($a->json('data.id'));
    expect(Conversation::where('type', 'direct')->count())->toBe(1);
});

it('rejects a direct conversation with yourself', function (): void {
    [$me, $token] = chatAdmin();

    $this->withToken($token)->postJson('/api/v1/admin/chat/conversations', [
        'type' => 'direct', 'user_ids' => [$me->id],
    ])->assertStatus(422);
});

// ─── Messaging + participation guard ────────────────────────────────────────

it('sends and lists messages for a participant', function (): void {
    [, $token] = chatAdmin();
    $other = User::factory()->create();
    $conv = $this->withToken($token)->postJson('/api/v1/admin/chat/conversations', [
        'type' => 'direct', 'user_ids' => [$other->id],
    ])->json('data.id');

    $this->withToken($token)->postJson("/api/v1/admin/chat/conversations/{$conv}/messages", [
        'body' => 'مرحباً',
    ])->assertCreated();

    $res = $this->withToken($token)->getJson("/api/v1/admin/chat/conversations/{$conv}/messages")->assertOk();
    expect($res->json('data.0.body'))->toBe('مرحباً');
    expect($res->json('data.0.mine'))->toBeTrue();
});

it('forbids a non-participant from reading or sending', function (): void {
    [$me, $token] = chatAdmin();
    $other = User::factory()->create();
    $conv = Conversation::create(['type' => 'group', 'title' => 'خاص', 'created_by' => $other->id]);
    $conv->participants()->create(['user_id' => $other->id]);

    $this->withToken($token)->getJson("/api/v1/admin/chat/conversations/{$conv->id}/messages")
        ->assertStatus(403);
    $this->withToken($token)->postJson("/api/v1/admin/chat/conversations/{$conv->id}/messages", [
        'body' => 'تطفّل',
    ])->assertStatus(403);
});

// ─── Attachments via MediaAsset ─────────────────────────────────────────────

it('sends a message with an attachment the sender owns', function (): void {
    [$me, $token] = chatAdmin();
    $other = User::factory()->create();
    $asset = plainImage(['uploaded_by' => $me->id]);
    $conv = $this->withToken($token)->postJson('/api/v1/admin/chat/conversations', [
        'type' => 'direct', 'user_ids' => [$other->id],
    ])->json('data.id');

    $res = $this->withToken($token)->postJson("/api/v1/admin/chat/conversations/{$conv}/messages", [
        'attachment_asset_id' => $asset->id,
    ])->assertCreated();

    expect($res->json('data.attachment.id'))->toBe($asset->id);
    expect($res->json('data.attachment.is_image'))->toBeTrue();
});

it('rejects an attachment the sender does not own (foreign asset id)', function (): void {
    [, $token] = chatAdmin();
    $other = User::factory()->create();
    $foreign = plainImage(['uploaded_by' => $other->id]);
    $conv = $this->withToken($token)->postJson('/api/v1/admin/chat/conversations', [
        'type' => 'direct', 'user_ids' => [$other->id],
    ])->json('data.id');

    $this->withToken($token)->postJson("/api/v1/admin/chat/conversations/{$conv}/messages", [
        'attachment_asset_id' => $foreign->id,
    ])->assertStatus(422);
});

it('keeps the previous message as list preview after the latest is deleted', function (): void {
    [, $token] = chatAdmin();
    $other = User::factory()->create();
    $conv = $this->withToken($token)->postJson('/api/v1/admin/chat/conversations', [
        'type' => 'direct', 'user_ids' => [$other->id],
    ])->json('data.id');

    $this->withToken($token)->postJson("/api/v1/admin/chat/conversations/{$conv}/messages", ['body' => 'A'])->assertCreated();
    $bId = $this->withToken($token)->postJson("/api/v1/admin/chat/conversations/{$conv}/messages", ['body' => 'B'])->json('data.id');

    $this->withToken($token)->deleteJson("/api/v1/admin/chat/messages/{$bId}")->assertOk();

    $list = $this->withToken($token)->getJson('/api/v1/admin/chat/conversations')->json('data');
    $mine = collect($list)->firstWhere('id', $conv);
    expect($mine['last_message']['body'])->toBe('A');
});

it('rejects a message with neither body nor attachment', function (): void {
    [, $token] = chatAdmin();
    $other = User::factory()->create();
    $conv = $this->withToken($token)->postJson('/api/v1/admin/chat/conversations', [
        'type' => 'direct', 'user_ids' => [$other->id],
    ])->json('data.id');

    $this->withToken($token)->postJson("/api/v1/admin/chat/conversations/{$conv}/messages", [])
        ->assertStatus(422);
});

// ─── Unread + mark read ─────────────────────────────────────────────────────

it('counts unread messages and clears them on read', function (): void {
    [$me, $tokenMe] = chatAdmin();
    [$peer, $tokenPeer] = chatAdmin();

    $conv = asUser($tokenMe)->postJson('/api/v1/admin/chat/conversations', [
        'type' => 'direct', 'user_ids' => [$peer->id],
    ])->json('data.id');

    // الطرف الآخر يرسل رسالتين.
    asUser($tokenPeer)->postJson("/api/v1/admin/chat/conversations/{$conv}/messages", ['body' => '1'])->assertCreated();
    asUser($tokenPeer)->postJson("/api/v1/admin/chat/conversations/{$conv}/messages", ['body' => '2'])->assertCreated();

    $list = asUser($tokenMe)->getJson('/api/v1/admin/chat/conversations')->json('data');
    $mine = collect($list)->firstWhere('id', $conv);
    expect($mine['unread_count'])->toBe(2);

    asUser($tokenMe)->postJson("/api/v1/admin/chat/conversations/{$conv}/read")->assertOk();

    $list2 = asUser($tokenMe)->getJson('/api/v1/admin/chat/conversations')->json('data');
    expect(collect($list2)->firstWhere('id', $conv)['unread_count'])->toBe(0);
});

// ─── Edit / delete own message ──────────────────────────────────────────────

it('edits and soft-deletes own message but not others', function (): void {
    [$me, $tokenMe] = chatAdmin();
    [$peer, $tokenPeer] = chatAdmin();
    $conv = asUser($tokenMe)->postJson('/api/v1/admin/chat/conversations', [
        'type' => 'direct', 'user_ids' => [$peer->id],
    ])->json('data.id');

    $msgId = asUser($tokenMe)->postJson("/api/v1/admin/chat/conversations/{$conv}/messages", [
        'body' => 'أصلي',
    ])->json('data.id');

    asUser($tokenMe)->patchJson("/api/v1/admin/chat/messages/{$msgId}", ['body' => 'معدّل'])->assertOk();
    expect(Message::find($msgId)->body)->toBe('معدّل');
    expect(Message::find($msgId)->edited_at)->not->toBeNull();

    // الطرف الآخر لا يملك تعديلها/حذفها.
    asUser($tokenPeer)->patchJson("/api/v1/admin/chat/messages/{$msgId}", ['body' => 'اختراق'])->assertStatus(403);
    asUser($tokenPeer)->deleteJson("/api/v1/admin/chat/messages/{$msgId}")->assertStatus(403);

    asUser($tokenMe)->deleteJson("/api/v1/admin/chat/messages/{$msgId}")->assertOk();
    expect(Message::withTrashed()->find($msgId)->trashed())->toBeTrue();
});

// ─── Tombstone: deleted message stays in the thread, content hidden ─────────

it('renders a deleted message as a tombstone (no original content leaked)', function (): void {
    [, $tokenMe] = chatAdmin();
    $other = User::factory()->create();
    $conv = asUser($tokenMe)->postJson('/api/v1/admin/chat/conversations', [
        'type' => 'direct', 'user_ids' => [$other->id],
    ])->json('data.id');

    $msgId = asUser($tokenMe)->postJson("/api/v1/admin/chat/conversations/{$conv}/messages", [
        'body' => 'محتوى سرّي',
    ])->json('data.id');

    asUser($tokenMe)->deleteJson("/api/v1/admin/chat/messages/{$msgId}")->assertOk();

    $res = asUser($tokenMe)->getJson("/api/v1/admin/chat/conversations/{$conv}/messages")->assertOk();
    $tomb = collect($res->json('data'))->firstWhere('id', $msgId);

    expect($tomb)->not->toBeNull();          // ما زالت في الخيط (لم تقفز)
    expect($tomb['deleted'])->toBeTrue();
    expect($tomb['body'])->toBeNull();        // لا تسريب للمحتوى الأصليّ
    expect($tomb['sender'])->toBeNull();
});

// ─── Auth + audit policy ────────────────────────────────────────────────────

it('denies chat without a token', function (): void {
    $this->getJson('/api/v1/admin/chat/conversations')->assertStatus(401);
});

it('audits conversation creation but NOT messages (architectural decision)', function (): void {
    [, $token] = chatAdmin();
    $other = User::factory()->create();
    $conv = $this->withToken($token)->postJson('/api/v1/admin/chat/conversations', [
        'type' => 'direct', 'user_ids' => [$other->id],
    ])->json('data.id');
    $this->withToken($token)->postJson("/api/v1/admin/chat/conversations/{$conv}/messages", ['body' => 'x'])->assertCreated();

    expect(Activity::where('log_name', 'conversation')->where('event', 'created')->exists())->toBeTrue();
    // الرسائل تيّار أحداث — مستثناة من التدقيق عمداً.
    expect(Activity::where('log_name', 'message')->exists())->toBeFalse();
});
