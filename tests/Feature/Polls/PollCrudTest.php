<?php

declare(strict_types=1);

use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\PollVoteOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => seedRoles());

/** توكن super_admin (كل الصلاحيات). */
function pollSuper(): string
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

it('creates a poll with options, forced inactive, and writes an activity log', function (): void {
    $res = $this->withToken(pollSuper())->postJson('/api/v1/admin/polls', [
        'question' => 'Best framework?',
        'allow_multiple' => false,
        'is_active' => true, // يجب تجاهله — التفعيل عبر نشر مستقلّ
        'options' => [
            ['label' => 'Laravel'],
            ['label' => 'Symfony'],
        ],
    ]);

    $res->assertStatus(201);
    expect($res->json('data.question'))->toBe('Best framework?')
        ->and($res->json('data.is_active'))->toBeFalse()      // لم يُفعَّل عبر الإنشاء
        ->and($res->json('data.options'))->toHaveCount(2);

    expect(Activity::where('log_name', 'poll')->where('event', 'created')->exists())->toBeTrue()
        ->and(Activity::where('log_name', 'poll_option')->where('event', 'created')->exists())->toBeTrue();
});

it('rejects a poll with fewer than two options', function (): void {
    $this->withToken(pollSuper())->postJson('/api/v1/admin/polls', [
        'question' => 'Only one?',
        'options' => [['label' => 'Solo']],
    ])->assertStatus(422);
});

it('rejects a poll without a question', function (): void {
    $this->withToken(pollSuper())->postJson('/api/v1/admin/polls', [
        'options' => [['label' => 'A'], ['label' => 'B']],
    ])->assertStatus(422);
});

it('updates a poll: relabel, add, and remove an unvoted option', function (): void {
    $poll = Poll::factory()->create(['question' => 'Old']);
    $a = PollOption::factory()->create(['poll_id' => $poll->id, 'label' => 'A', 'sort_order' => 0]);
    $b = PollOption::factory()->create(['poll_id' => $poll->id, 'label' => 'B', 'sort_order' => 1]);

    $res = $this->withToken(pollSuper())->putJson("/api/v1/admin/polls/{$poll->id}", [
        'question' => 'New',
        'options' => [
            ['id' => $a->id, 'label' => 'A renamed'],
            ['label' => 'C added'],
        ],
    ]);

    $res->assertOk();
    expect($poll->fresh()->question)->toBe('New')
        ->and(PollOption::whereKey($b->id)->exists())->toBeFalse()       // غير مُصوَّت ⇒ يُحذف
        ->and(PollOption::whereKey($a->id)->value('label'))->toBe('A renamed')
        ->and($poll->options()->count())->toBe(2);
});

it('forbids deleting an option that already has votes (decision A)', function (): void {
    $poll = Poll::factory()->create();
    $a = PollOption::factory()->create(['poll_id' => $poll->id, 'label' => 'A']);
    $b = PollOption::factory()->create(['poll_id' => $poll->id, 'label' => 'B']);

    // صوت حقيقيّ على الخيار A (مصدر الحقيقة).
    $vote = PollVote::factory()->create(['poll_id' => $poll->id]);
    PollVoteOption::factory()->create(['poll_vote_id' => $vote->id, 'poll_option_id' => $a->id]);

    // محاولة إزالة A (يملك صوتاً) ⇒ رفض كامل دون تغيير.
    $this->withToken(pollSuper())->putJson("/api/v1/admin/polls/{$poll->id}", [
        'question' => $poll->question,
        'options' => [['id' => $b->id, 'label' => 'B']],
    ])->assertStatus(422);

    expect(PollOption::whereKey($a->id)->exists())->toBeTrue()
        ->and($poll->options()->count())->toBe(2);
});

it('allows relabeling a voted option (only deletion is blocked)', function (): void {
    $poll = Poll::factory()->create();
    $a = PollOption::factory()->create(['poll_id' => $poll->id, 'label' => 'A']);
    $b = PollOption::factory()->create(['poll_id' => $poll->id, 'label' => 'B']);
    $vote = PollVote::factory()->create(['poll_id' => $poll->id]);
    PollVoteOption::factory()->create(['poll_vote_id' => $vote->id, 'poll_option_id' => $a->id]);

    $this->withToken(pollSuper())->putJson("/api/v1/admin/polls/{$poll->id}", [
        'question' => $poll->question,
        'options' => [
            ['id' => $a->id, 'label' => 'A renamed'],
            ['id' => $b->id, 'label' => 'B'],
        ],
    ])->assertOk();

    expect(PollOption::whereKey($a->id)->value('label'))->toBe('A renamed');
});

it('toggles active state', function (): void {
    $poll = Poll::factory()->inactive()->create();

    $this->withToken(pollSuper())->patchJson("/api/v1/admin/polls/{$poll->id}/active")->assertOk();
    expect($poll->fresh()->is_active)->toBeTrue();

    $this->withToken(pollSuper())->patchJson("/api/v1/admin/polls/{$poll->id}/active")->assertOk();
    expect($poll->fresh()->is_active)->toBeFalse();
});

it('soft-deletes then restores a poll', function (): void {
    $poll = Poll::factory()->create();

    $this->withToken(pollSuper())->deleteJson("/api/v1/admin/polls/{$poll->id}")->assertOk();
    expect(Poll::whereKey($poll->id)->exists())->toBeFalse()
        ->and(Poll::withTrashed()->whereKey($poll->id)->exists())->toBeTrue();

    $this->withToken(pollSuper())->postJson("/api/v1/admin/polls/{$poll->id}/restore")->assertOk();
    expect(Poll::whereKey($poll->id)->exists())->toBeTrue();
});

it('force-deletes a poll and cascades to options and votes', function (): void {
    $poll = Poll::factory()->create();
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);
    $vote = PollVote::factory()->create(['poll_id' => $poll->id]);
    PollVoteOption::factory()->create(['poll_vote_id' => $vote->id, 'poll_option_id' => $option->id]);

    $this->withToken(pollSuper())->deleteJson("/api/v1/admin/polls/{$poll->id}/force")->assertOk();

    expect(Poll::withTrashed()->whereKey($poll->id)->exists())->toBeFalse()
        ->and(PollOption::whereKey($option->id)->exists())->toBeFalse()
        ->and(PollVote::whereKey($vote->id)->exists())->toBeFalse()
        ->and(PollVoteOption::where('poll_vote_id', $vote->id)->exists())->toBeFalse();
});

it('lists polls and a trashed-only view', function (): void {
    Poll::factory()->count(2)->create();
    $trashed = Poll::factory()->create();
    $trashed->delete();

    $token = pollSuper();

    $live = $this->withToken($token)->getJson('/api/v1/admin/polls')->assertOk();
    expect($live->json('data'))->toHaveCount(2);

    $only = $this->withToken($token)->getJson('/api/v1/admin/polls?trashed=only')->assertOk();
    expect($only->json('data'))->toHaveCount(1);
});
