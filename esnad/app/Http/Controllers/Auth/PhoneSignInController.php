<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Governorate;
use App\Models\User;
use App\Services\ConsentService;
use App\Services\Otp\OtpService;
use App\Services\Settings;
use App\Services\Turnstile;
use App\Support\Digits;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Client sign-up and sign-in with an Egyptian mobile number and a one-time password. */
class PhoneSignInController extends Controller
{
    public function __construct(private OtpService $otp) {}

    public function create(): View
    {
        return view('auth.sign-in');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['phone' => ['required', 'string', 'max:24']]);

        if (! Turnstile::verify($request->input('cf-turnstile-response'), $request->ip())) {
            throw ValidationException::withMessages(['phone' => __('auth.bot_check_failed')]);
        }

        $phone = Phone::normalise($request->input('phone'));
        if (! $phone) {
            throw ValidationException::withMessages(['phone' => __('auth.phone_invalid')]);
        }

        return $this->sendCode($request, $phone);
    }

    public function verifyForm(Request $request): View|RedirectResponse
    {
        $phone = $request->session()->get('otp.phone');
        if (! $phone) {
            return redirect()->route('auth.sign-in')->withErrors(['phone' => __('auth.session_expired')]);
        }

        return view('auth.verify', [
            'phone' => $phone,
            'channel' => $request->session()->get('otp.channel', 'whatsapp'),
            'resendIn' => max(0, (int) $request->session()->get('otp.sent_at', 0) + config('esnad.otp.resend_after_seconds') - time()),
        ]);
    }

    public function resend(Request $request): RedirectResponse
    {
        $phone = $request->session()->get('otp.phone');
        if (! $phone) {
            return redirect()->route('auth.sign-in')->withErrors(['phone' => __('auth.session_expired')]);
        }

        return $this->sendCode($request, $phone);
    }

    public function verify(Request $request): RedirectResponse
    {
        $phone = $request->session()->get('otp.phone');
        if (! $phone) {
            return redirect()->route('auth.sign-in')->withErrors(['phone' => __('auth.session_expired')]);
        }
        $code = preg_replace('/\D/', '', Digits::toWestern((string) $request->input('code')));
        $request->merge(['code' => $code]);
        $request->validate(['code' => ['required', 'digits:'.config('esnad.otp.length')]]);

        // Per IP limit on verification attempts, on top of the per-code limit.
        $key = 'otp-verify:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 30)) {
            throw ValidationException::withMessages(['code' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)])]);
        }
        RateLimiter::hit($key, 3600);

        if (! $this->otp->verify($phone, 'sign_in', $code)) {
            throw ValidationException::withMessages(['code' => __('auth.code_invalid')]);
        }

        $user = User::withTrashed()->firstWhere('phone', $phone);
        if ($user?->trashed() || ($user && $user->status !== 'active')) {
            throw ValidationException::withMessages(['code' => __('auth.failed')]);
        }
        $user ??= User::create(['phone' => $phone, 'locale' => app()->getLocale()]);
        $user->forceFill(['phone_verified_at' => $user->phone_verified_at ?? now(), 'last_seen_at' => now()])->save();

        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $request->session()->forget(['otp.phone', 'otp.channel', 'otp.sent_at']);

        return redirect()->intended(route('account'));
    }

    public function welcomeForm(Request $request): View
    {
        return view('auth.welcome', [
            'user' => $request->user(),
            'governorates' => Governorate::query()->orderBy('sort')->get(),
            'transferRequired' => (bool) Settings::get('data_stored_abroad', true),
        ]);
    }

    public function welcomeStore(Request $request): RedirectResponse
    {
        $transferRequired = (bool) Settings::get('data_stored_abroad', true);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'address_form' => ['nullable', Rule::in(['m', 'f'])],
            'governorate_id' => ['required', 'exists:governorates,id'],
            'consent_service' => ['accepted'],
            'consent_transfer_abroad' => [$transferRequired ? 'accepted' : 'nullable'],
        ], [
            'consent_service.accepted' => __('consent.service_required'),
            'consent_transfer_abroad.accepted' => __('consent.transfer_required'),
        ]);

        $user = $request->user();
        $nameField = preg_match('/\p{Arabic}/u', $data['name']) ? 'name_ar' : 'name_en';
        $user->fill([$nameField => $data['name'], 'address_form' => $data['address_form'] ?? null, 'governorate_id' => $data['governorate_id']])->save();

        foreach (ConsentService::purposes() as $purpose) {
            if ($request->boolean('consent_'.$purpose)) {
                ConsentService::give($user, $purpose);
            }
        }

        return redirect()->intended(route('account'))->with('status', __('auth.signed_in'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', __('auth.signed_out'));
    }

    private function sendCode(Request $request, string $phone): RedirectResponse
    {
        $result = $this->otp->send($phone, 'sign_in', (string) $request->ip(), app()->getLocale());

        $message = match ($result['status']) {
            OtpService::RESULT_TOO_SOON => __('auth.too_soon', ['seconds' => $result['retry_after']]),
            OtpService::RESULT_THROTTLED => __('auth.throttled', ['minutes' => (int) ceil($result['retry_after'] / 60)]),
            OtpService::RESULT_UNDELIVERED => __('auth.undelivered'),
            default => null,
        };

        $request->session()->put('otp.phone', $phone);
        if ($result['status'] === OtpService::RESULT_SENT) {
            $request->session()->put('otp.channel', $result['channel']);
            $request->session()->put('otp.sent_at', time());

            return redirect()->route('auth.verify');
        }
        if ($result['status'] === OtpService::RESULT_TOO_SOON && $request->routeIs('auth.resend')) {
            return redirect()->route('auth.verify')->withErrors(['code' => $message]);
        }

        return redirect()->route('auth.sign-in')->withInput()->withErrors(['phone' => $message]);
    }
}
