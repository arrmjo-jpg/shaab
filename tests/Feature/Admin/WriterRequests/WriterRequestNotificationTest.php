<?php

declare(strict_types=1);

use App\Actions\Admin\WriterRequests\ApproveWriterRequestAction;
use App\Actions\Admin\WriterRequests\RejectWriterRequestAction;
use App\Models\User;
use App\Models\WriterRequest;
use App\Notifications\ApproveWriterRequestNotification;
use App\Notifications\RejectWriterRequestNotification;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function wrApplicant(): User
{
    return User::factory()->create(['is_writer' => false]);
}

function wrAdmin(): User
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u;
}

function wrPending(User $applicant): WriterRequest
{
    return WriterRequest::create(['user_id' => $applicant->id, 'status' => 'pending', 'note' => 'vfy']);
}

// ─── Approve: database + mail (queued) ────────────────────────────────────

it('queues a database + mail notification to the applicant on approve', function (): void {
    Notification::fake();
    $applicant = wrApplicant();

    (new ApproveWriterRequestAction)->handle(wrPending($applicant), wrAdmin());

    Notification::assertSentTo(
        $applicant,
        ApproveWriterRequestNotification::class,
        fn ($n, array $channels) => in_array('database', $channels, true) && in_array('mail', $channels, true),
    );
});

it('writes a real database notification row on approve', function (): void {
    $applicant = wrApplicant();

    (new ApproveWriterRequestAction)->handle(wrPending($applicant), wrAdmin());

    expect($applicant->fresh()->notifications()->count())->toBe(1);
    $data = $applicant->fresh()->notifications()->first()->data;
    expect($data['kind'])->toBe('writer_request');
    expect($data['event'])->toBe('approved');
});

// ─── Reject: mail only, no database ───────────────────────────────────────

it('sends mail only on reject (channels === [mail])', function (): void {
    Notification::fake();
    $applicant = wrApplicant();

    (new RejectWriterRequestAction)->handle(wrPending($applicant), wrAdmin());

    Notification::assertSentTo(
        $applicant,
        RejectWriterRequestNotification::class,
        fn ($n, array $channels) => $channels === ['mail'],
    );
});

it('creates NO database notification on reject', function (): void {
    $applicant = wrApplicant();

    (new RejectWriterRequestAction)->handle(wrPending($applicant), wrAdmin());

    expect(DatabaseNotification::count())->toBe(0);
});

// ─── Queued (ShouldQueue) ─────────────────────────────────────────────────

it('both writer-request notifications are queued (ShouldQueue)', function (): void {
    expect(new ApproveWriterRequestNotification)->toBeInstanceOf(ShouldQueue::class);
    expect(new RejectWriterRequestNotification)->toBeInstanceOf(ShouldQueue::class);
});

// ─── Best-effort: mail transport failure never breaks the action ──────────

it('approve still succeeds (and writes db row) when mail transport fails', function (): void {
    Log::spy();
    $applicant = wrApplicant();
    $wr = wrPending($applicant);

    // البريد يرمي عند الإرسال (sync) → القناة mail تفشل → يُلتقَط best-effort.
    $this->instance(MailFactory::class, Mockery::mock(MailFactory::class, function ($m): void {
        $m->shouldReceive('mailer')->andThrow(new RuntimeException('SMTP down'));
    }));

    $res = (new ApproveWriterRequestAction)->handle($wr, wrAdmin());

    expect($res->getStatusCode())->toBe(200);
    expect($wr->fresh()->status->value)->toBe('approved');
    expect($applicant->fresh()->notifications()->count())->toBe(1); // قناة database سبقت فشل mail
    Log::shouldHaveReceived('warning')->atLeast()->once();
});

it('reject still succeeds when mail transport fails', function (): void {
    Log::spy();
    $applicant = wrApplicant();
    $wr = wrPending($applicant);

    $this->instance(MailFactory::class, Mockery::mock(MailFactory::class, function ($m): void {
        $m->shouldReceive('mailer')->andThrow(new RuntimeException('SMTP down'));
    }));

    $res = (new RejectWriterRequestAction)->handle($wr, wrAdmin());

    expect($res->getStatusCode())->toBe(200);
    expect($wr->fresh()->status->value)->toBe('rejected');
    expect(DatabaseNotification::count())->toBe(0);
    Log::shouldHaveReceived('warning')->atLeast()->once();
});

// ─── Idempotency: non-pending → no notification ───────────────────────────

it('does not notify when the request is not pending', function (): void {
    Notification::fake();
    $applicant = wrApplicant();
    $wr = WriterRequest::create(['user_id' => $applicant->id, 'status' => 'approved', 'note' => 'vfy']);

    $res = (new ApproveWriterRequestAction)->handle($wr, wrAdmin());

    expect($res->getStatusCode())->toBe(422);
    Notification::assertNothingSent();
});
