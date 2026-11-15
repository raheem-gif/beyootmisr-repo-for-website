<?php

use App\Models\AccessLog;
use App\Models\Page;
use Database\Seeders\LegalPageSeeder;

beforeEach(fn () => seedBase());

it('redirects the root to Arabic by default and to English for English browsers', function () {
    $this->withHeader('Accept-Language', 'ar-EG,ar;q=0.9')->get('/')->assertRedirect('/ar');
    $this->withHeader('Accept-Language', 'en-GB,en;q=0.9')->get('/')->assertRedirect('/en');
});

it('renders the home page right to left in Arabic and left to right in English', function () {
    $this->get('/ar')->assertOk()->assertSee('dir="rtl"', false)->assertSee('lang="ar"', false)->assertSee('اطلب محامي الآن');
    $this->get('/en')->assertOk()->assertSee('dir="ltr"', false)->assertSee('Request a lawyer now');
});

it('hides the platform numbers band when there are no verified lawyers', function () {
    $this->get('/ar')->assertDontSee('id="numbers-title"', false);
});

it('sends security headers and a nonce-based content security policy', function () {
    $response = $this->get('/ar');
    $response->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY');
    expect($response->headers->get('Content-Security-Policy'))->toContain("script-src 'self' 'nonce-")->not->toContain('unsafe-eval');
});

it('shows every legal page in both languages', function (string $slug) {
    $this->get("/ar/legal/$slug")->assertOk()->assertSee(__("site.legal_pages.$slug", [], 'ar'));
    $this->get("/en/legal/$slug")->assertOk()->assertSee(__("site.legal_pages.$slug", [], 'en'));
})->with(LegalPageSeeder::SLUGS);

it('strips scripts from page bodies', function () {
    Page::where('slug', 'terms')->update(['body_ar' => '<p>نص</p><script>alert(1)</script><a href="javascript:alert(1)" onclick="x()">رابط</a>']);
    $this->get('/ar/legal/terms')->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('onclick', false)->assertDontSee('javascript:', false);
});

it('fills operator identity in legal pages from configuration', function () {
    config(['esnad.operator.company_name' => 'شركة اختبار']);
    $this->get('/ar/legal/terms')->assertSee('شركة اختبار');
});

it('says search and request now are not open while their flags are off', function () {
    $this->get('/ar/search')->assertOk()->assertSee(__('site.not_open.title', [], 'ar'));
    $this->get('/ar/request')->assertOk()->assertSee(__('site.not_open.request', [], 'ar'));
});

it('returns localised 404 pages', function () {
    $this->get('/en/missing-page')->assertNotFound()->assertSee('This page does not exist');
    $this->get('/ar/missing-page')->assertNotFound()->assertSee('الصفحة دي مش موجودة');
});

it('writes an access log entry without query strings', function () {
    $this->get('/ar/search?q=secret');
    $log = AccessLog::latest('id')->first();
    expect($log->route)->toBe('search')->and($log->route)->not->toContain('secret');
});

it('hides the design gallery in production', function () {
    $this->get('/ar/design')->assertOk();
    app()->detectEnvironment(fn () => 'production');
    $this->get('/ar/design')->assertNotFound();
});
