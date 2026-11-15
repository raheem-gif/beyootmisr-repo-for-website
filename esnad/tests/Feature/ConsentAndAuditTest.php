<?php

use App\Models\AuditLog;
use App\Models\Consent;
use App\Models\User;
use App\Services\Audit;
use App\Services\ConsentService;
use App\Services\Settings;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => seedBase());

function client(): User
{
    $user = User::create(['phone' => '+201001234567', 'name_ar' => 'عميل', 'governorate_id' => 1]);
    $user->assignRole('client');
    ConsentService::give($user, 'service');

    return $user;
}

it('records each consent with version, method and time, and lets the user withdraw it', function () {
    $user = client();
    $this->actingAs($user)->post('/ar/account/consents/marketing')->assertRedirect();
    $consent = ConsentService::current($user, 'marketing');
    expect($consent->version)->toBe(config('esnad.consent_version'))->and($consent->method)->toBe('web_checkbox')->and($consent->given_at)->not->toBeNull();

    $this->actingAs($user)->delete('/ar/account/consents/marketing')->assertRedirect();
    expect(ConsentService::has($user, 'marketing'))->toBeFalse()
        ->and(Consent::where('purpose', 'marketing')->first()->withdrawn_at)->not->toBeNull();

    // Giving it again makes a new row; history keeps both.
    $this->actingAs($user)->post('/ar/account/consents/marketing');
    expect(Consent::where('purpose', 'marketing')->count())->toBe(2);
});

it('does not let the service consent be withdrawn from the consent list', function () {
    $this->actingAs(client())->delete('/ar/account/consents/service')->assertNotFound();
});

it('refuses edits and deletes of consent records', function () {
    $consent = ConsentService::current(client(), 'service');
    expect(fn () => $consent->update(['version' => 'x']))->toThrow(LogicException::class);
    expect(fn () => $consent->delete())->toThrow(LogicException::class);
});

it('keeps the audit log append-only with an intact hash chain', function () {
    Audit::record('test.one');
    Audit::record('test.two', null, ['a' => 1], ['a' => 2, 'password' => 'secret']);
    $log = AuditLog::latest('id')->first();
    expect($log->after['password'])->toBe('[redacted]');
    expect(Audit::verifyChain())->toBeNull();
    expect(fn () => $log->update(['action' => 'x']))->toThrow(LogicException::class);
    expect(fn () => $log->delete())->toThrow(LogicException::class);

    // Tampering through the query builder breaks the chain.
    DB::table('audit_logs')->where('id', $log->id)->update(['action' => 'forged']);
    expect(Audit::verifyChain())->toBe($log->id);
});

it('versions settings and audits each change', function () {
    Settings::set('verification_target_hours', 36, 'test');
    Settings::set('verification_target_hours', 24, 'test again');
    expect(Settings::get('verification_target_hours'))->toBe(24);
    $this->assertDatabaseHas('settings', ['key' => 'verification_target_hours', 'version' => 3]);
    expect(DB::table('setting_versions')->where('key', 'verification_target_hours')->count())->toBe(2);
    expect(AuditLog::where('action', 'settings.updated')->count())->toBe(2);
});
