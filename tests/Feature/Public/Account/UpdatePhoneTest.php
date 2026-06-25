<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Enums\WhatsappContactStatus;
use App\Models\User;
use App\Models\WhatsappContact;
use App\Models\WhatsappGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// نافذة ما بعد الدخول — حفظ رقم الهاتف + اختيار الاشتراك في حملات واتساب، مع مزامنة الاشتراك
// مع نظام whatsapp_contacts القائم (SSoT للإرسال) لا مجرّد علم على users.

function phoneUser(): array
{
    $user = User::factory()->create(['status' => UserStatus::Active, 'phone' => null]);
    $token = $user->createToken('public', ['user'])->plainTextToken;

    return [$user, $token];
}

it('saves the phone, sets the flag, and subscribes the contact into the default group', function (): void {
    $group = WhatsappGroup::create(['name' => 'مشتركو الموقع', 'is_default' => true]);
    [$user, $token] = phoneUser();

    $res = $this->withToken($token)
        ->patchJson('/api/v1/account/phone', [
            'phone' => '+962791234567',
            'whatsapp_subscribed' => true,
        ])
        ->assertOk();

    // المستخدم: الرقم مُطبَّع E.164 + العلم مرفوع.
    $user->refresh();
    expect($user->phone)->toBe('+962791234567');
    expect($user->whatsapp_subscribed)->toBeTrue();
    expect($res->json('data.phone'))->toBe('+962791234567');
    expect($res->json('data.whatsapp_subscribed'))->toBeTrue();

    // النظام القائم: جهة اتصال مشتركة فعلاً، مُسنَدة للمجموعة الافتراضية (يقرؤها مُرسِل الحملات).
    $contact = WhatsappContact::where('phone', '+962791234567')->first();
    expect($contact)->not->toBeNull();
    expect($contact->status)->toBe(WhatsappContactStatus::Subscribed);
    expect($contact->groups()->whereKey($group->id)->exists())->toBeTrue();
});

it('unsubscribes the existing contact when the box is left unchecked', function (): void {
    WhatsappGroup::create(['name' => 'مشتركو الموقع', 'is_default' => true]);
    $contact = WhatsappContact::create([
        'name' => 'سابق', 'phone' => '+962791234567',
        'status' => WhatsappContactStatus::Subscribed->value, 'source' => 'api',
    ]);
    [$user, $token] = phoneUser();

    $this->withToken($token)
        ->patchJson('/api/v1/account/phone', [
            'phone' => '+962791234567',
            'whatsapp_subscribed' => false,
        ])
        ->assertOk();

    $user->refresh();
    expect($user->phone)->toBe('+962791234567');
    expect($user->whatsapp_subscribed)->toBeFalse();

    // لا حذف — فقط status=unsubscribed (يُستبعَد من المستلمين).
    expect($contact->fresh()->status)->toBe(WhatsappContactStatus::Unsubscribed);
});

it('rejects a local (non-international) phone number', function (): void {
    [, $token] = phoneUser();

    $this->withToken($token)
        ->patchJson('/api/v1/account/phone', ['phone' => '0791234567', 'whatsapp_subscribed' => true])
        ->assertStatus(422);

    expect(WhatsappContact::count())->toBe(0);
});

it('requires authentication for /account/phone', function (): void {
    $this->patchJson('/api/v1/account/phone', ['phone' => '+962791234567'])
        ->assertUnauthorized();
});
