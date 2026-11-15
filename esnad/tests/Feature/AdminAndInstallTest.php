<?php

use App\Console\Commands\CopyCheck;
use App\Filament\Resources\AuditLogResource;
use App\Filament\Resources\ConsentResource;
use App\Filament\Resources\PageResource;
use App\Filament\Resources\SettingResource;
use App\Models\Consent;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(fn () => seedBase());

it('seeds every role from section 5', function () {
    expect(Role::pluck('name')->all())->toContain('client', 'business_client', 'lawyer', 'trainee_lawyer', 'firm_admin', 'office_staff',
        'legal_aid_partner', 'support_agent', 'verification_officer', 'dispatch_operator', 'finance_officer', 'content_editor',
        'data_protection_officer', 'super_admin');
});

it('keeps clients out of the admin', function () {
    $client = User::create(['phone' => '+201001234567', 'name_ar' => 'عميل']);
    $client->assignRole('client');
    $this->actingAs($client)->get('/admin')->assertForbidden();
});

it('requires staff to set up two-factor sign-in before using the admin', function () {
    $admin = User::create(['email' => 'admin@example.test', 'password' => 'a-long-password-123', 'name_en' => 'Admin']);
    $admin->assignRole('super_admin');
    $response = $this->actingAs($admin)->get('/admin');
    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('multi-factor-authentication');
});

it('lets a content editor manage pages but not read the audit log', function () {
    $editor = User::create(['email' => 'ed@example.test', 'password' => 'a-long-password-123']);
    $editor->assignRole('content_editor');
    expect(PageResource::canViewAny())->toBeFalse(); // not signed in
    $this->actingAs($editor);
    expect(PageResource::canViewAny())->toBeTrue()
        ->and(AuditLogResource::canViewAny())->toBeFalse()
        ->and(ConsentResource::canViewAny())->toBeFalse()
        ->and(SettingResource::canViewAny())->toBeFalse();
});

it('lets the data protection officer read consents and audit but edit neither', function () {
    $dpo = User::create(['email' => 'dpo@example.test', 'password' => 'a-long-password-123']);
    $dpo->assignRole('data_protection_officer');
    $this->actingAs($dpo);
    $consent = Consent::create(['user_id' => $dpo->id, 'purpose' => 'service', 'version' => 'v', 'method' => 'web_checkbox', 'given_at' => now()]);
    expect(ConsentResource::canViewAny())->toBeTrue()
        ->and(ConsentResource::canEdit($consent))->toBeFalse()
        ->and(ConsentResource::canDelete($consent))->toBeFalse()
        ->and(AuditLogResource::canViewAny())->toBeTrue();
});

it('hides /install/check without the token and runs it with the token', function () {
    $this->get('/install/check')->assertNotFound();
    $this->get('/install/check?token=wrong')->assertNotFound();
    $this->get('/install/check?offline=1&token='.config('esnad.install.token'))
        ->assertOk()->assertSee('PHP 8.2 or later')->assertSee('pdo_mysql')->assertSee('Cron runs schedule:run every minute');
});

it('records scheduler and queue heartbeats', function () {
    $this->artisan('esnad:heartbeat')->assertSuccessful();
    $this->assertDatabaseHas('system_heartbeats', ['name' => 'scheduler']);
    $this->assertDatabaseHas('system_heartbeats', ['name' => 'queue']); // sync queue in tests
});

it('creates a super admin from the command line', function () {
    $this->artisan('esnad:create-admin', ['email' => 'owner@example.test', '--password' => 'owner-password-2026'])->assertSuccessful();
    expect(User::firstWhere('email', 'owner@example.test')->hasRole('super_admin'))->toBeTrue();
});

it('passes the copy check on every translation and content file', function () {
    expect(CopyCheck::scan(base_path()))->toBe([]);
});

it('has the same translation keys in Arabic and English', function () {
    foreach (glob(lang_path('ar/*.php')) as $file) {
        $name = basename($file);
        $flat = fn ($a, $p = '') => collect($a)->flatMap(fn ($v, $k) => is_array($v) && ! array_is_list($v) ? $GLOBALS['__flat']($v, "$p$k.") : ["$p$k"]);
        $GLOBALS['__flat'] = $flat;
        $ar = $flat(require $file)->sort()->values()->all();
        $en = $flat(require lang_path("en/$name"))->sort()->values()->all();
        if ($name === 'validation.php') {
            expect(array_values(array_diff($ar, $en, ['custom'])))->toBe([]);   // English has more framework rules
        } else {
            expect($ar)->toBe($en, "Keys differ in $name");
        }
    }
});
