<?php

use App\Models\OtpCode;
use App\Models\User;
use App\Services\ConsentService;
use App\Services\Otp\OtpChannel;
use App\Services\Otp\OtpDeliveryFailed;
use App\Services\Otp\OtpService;

/** A channel that keeps the last code so the test can type it. */
class CapturingChannel implements OtpChannel
{
    public ?string $code = null;

    public int $sent = 0;

    public function __construct(private string $id, private bool $fail = false) {}

    public function name(): string
    {
        return $this->id;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function send(string $phoneE164, string $code, string $locale): ?string
    {
        if ($this->fail) {
            throw new OtpDeliveryFailed('down');
        }
        $this->code = $code;
        $this->sent++;

        return 'msg-1';
    }
}

beforeEach(function () {
    seedBase();
    $this->whatsapp = new CapturingChannel('whatsapp');
    $this->sms = new CapturingChannel('sms');
    $this->app->instance(OtpService::class, new OtpService(['whatsapp' => $this->whatsapp, 'sms' => $this->sms]));
});

it('signs up a new client with WhatsApp OTP, Eastern digits and the welcome step', function () {
    $this->post('/ar/sign-in', ['phone' => '٠١٠٠١٢٣٤٥٦٧'])->assertRedirect('/ar/sign-in/code');
    expect($this->whatsapp->sent)->toBe(1)->and($this->sms->sent)->toBe(0);
    expect(OtpCode::first()->code_hash)->not->toBe($this->whatsapp->code);

    $this->get('/ar/sign-in/code')->assertOk()->assertSee('0100 123 4567');

    $eastern = strtr($this->whatsapp->code, array_combine(range(0, 9), ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩']));
    $this->post('/ar/sign-in/code', ['code' => $eastern])->assertRedirect('/ar/account');
    $user = User::firstWhere('phone', '+201001234567');
    expect($user)->not->toBeNull()->and($user->phone_verified_at)->not->toBeNull();
    $this->assertAuthenticatedAs($user);

    // The account needs the welcome step first.
    $this->get('/ar/account')->assertRedirect('/ar/welcome');

    $this->post('/ar/welcome', ['name' => 'منى أحمد', 'governorate_id' => 1])
        ->assertSessionHasErrors(['consent_service', 'consent_transfer_abroad']);

    $this->post('/ar/welcome', ['name' => 'منى أحمد', 'address_form' => 'f', 'governorate_id' => 1, 'consent_service' => '1', 'consent_transfer_abroad' => '1'])
        ->assertRedirect('/ar/account');

    $user->refresh();
    expect($user->name_ar)->toBe('منى أحمد')->and($user->address_form)->toBe('f');
    expect(ConsentService::has($user, 'service'))->toBeTrue()
        ->and(ConsentService::has($user, 'transfer_abroad'))->toBeTrue()
        ->and(ConsentService::has($user, 'marketing'))->toBeFalse()
        ->and(ConsentService::has($user, 'sensitive_data'))->toBeFalse();
    $this->get('/ar/account')->assertOk()->assertSee('منى أحمد');
});

it('falls back to SMS when WhatsApp fails', function () {
    $this->app->instance(OtpService::class, new OtpService(['whatsapp' => new CapturingChannel('whatsapp', fail: true), 'sms' => $this->sms]));
    $this->post('/en/sign-in', ['phone' => '01001234567'])->assertRedirect('/en/sign-in/code');
    expect($this->sms->sent)->toBe(1);
    $this->get('/en/sign-in/code')->assertSee('by text message');
    $this->assertDatabaseHas('delivery_logs', ['channel' => 'whatsapp', 'status' => 'failed']);
    $this->assertDatabaseHas('delivery_logs', ['channel' => 'sms', 'status' => 'sent']);
});

it('rejects numbers that are not Egyptian mobiles', function () {
    $this->post('/ar/sign-in', ['phone' => '0223456789'])->assertSessionHasErrors('phone');
    expect($this->whatsapp->sent)->toBe(0);
});

it('rejects a wrong code and locks the code after five attempts', function () {
    $this->post('/ar/sign-in', ['phone' => '01001234567']);
    $wrong = $this->whatsapp->code === '000000' ? '111111' : '000000';
    foreach (range(1, 5) as $i) {
        $this->post('/ar/sign-in/code', ['code' => $wrong])->assertSessionHasErrors('code');
    }
    $this->post('/ar/sign-in/code', ['code' => $this->whatsapp->code])->assertSessionHasErrors('code');
    $this->assertGuest();
});

it('does not resend within 60 seconds and limits sends per number', function () {
    $this->post('/ar/sign-in', ['phone' => '01001234567']);
    $this->post('/ar/sign-in/resend')->assertSessionHasErrors('code');
    expect($this->whatsapp->sent)->toBe(1);

    foreach (range(1, 4) as $i) {
        $this->travel(61)->seconds();
        $this->post('/ar/sign-in/resend');
    }
    expect($this->whatsapp->sent)->toBe(5);
    $this->travel(61)->seconds();
    $this->post('/ar/sign-in/resend')->assertSessionHasErrors('phone');
    expect($this->whatsapp->sent)->toBe(5);
});

it('expires codes after five minutes', function () {
    $this->post('/ar/sign-in', ['phone' => '01001234567']);
    $this->travel(301)->seconds();
    $this->post('/ar/sign-in/code', ['code' => $this->whatsapp->code])->assertSessionHasErrors('code');
    $this->assertGuest();
});

it('refuses suspended accounts', function () {
    User::create(['phone' => '+201001234567', 'status' => 'suspended']);
    $this->post('/ar/sign-in', ['phone' => '01001234567']);
    $this->post('/ar/sign-in/code', ['code' => $this->whatsapp->code])->assertSessionHasErrors('code');
    $this->assertGuest();
});
