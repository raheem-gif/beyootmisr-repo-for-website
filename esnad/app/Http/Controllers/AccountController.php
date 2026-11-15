<?php

namespace App\Http\Controllers;

use App\Models\Governorate;
use App\Services\ConsentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('account.show', [
            'user' => $user,
            'governorates' => Governorate::query()->orderBy('sort')->get(),
            'current' => collect(ConsentService::purposes())->mapWithKeys(fn ($p) => [$p => ConsentService::current($user, $p)]),
            'history' => ConsentService::history($user),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'address_form' => ['nullable', Rule::in(['m', 'f'])],
            'governorate_id' => ['required', 'exists:governorates,id'],
            'locale' => ['required', Rule::in(config('esnad.locales'))],
        ]);
        $user = $request->user();
        $nameField = preg_match('/\p{Arabic}/u', $data['name']) ? 'name_ar' : 'name_en';
        $user->fill([$nameField => $data['name'], 'address_form' => $data['address_form'] ?? null, 'governorate_id' => $data['governorate_id'], 'locale' => $data['locale']])->save();

        return back()->with('status', __('account.saved'));
    }

    public function giveConsent(Request $request, string $purpose): RedirectResponse
    {
        abort_unless(in_array($purpose, ConsentService::purposes(), true), 404);
        ConsentService::give($request->user(), $purpose);

        return back()->with('status', __('consent.saved'));
    }

    public function withdrawConsent(Request $request, string $purpose): RedirectResponse
    {
        // Withdrawing the service consent is an account deletion request (phase 8 privacy centre).
        abort_unless(in_array($purpose, ConsentService::purposes(), true) && $purpose !== 'service', 404);
        ConsentService::withdraw($request->user(), $purpose);

        return back()->with('status', __('consent.withdrawn'));
    }
}
