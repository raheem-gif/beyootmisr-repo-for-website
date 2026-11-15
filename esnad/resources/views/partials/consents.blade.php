{{-- Four separate, unticked consents (PDPL). Each is recorded on its own. --}}
<fieldset class="space-y-3">
    <legend class="text-xl font-semibold mb-1">{{ __('consent.title') }}</legend>
    <p class="text-ink-2 text-[15px] mb-3">{{ __('consent.intro') }} <a class="link" href="{{ route('page', 'privacy') }}" target="_blank">{{ __('consent.privacy_link') }}</a></p>
    @foreach (config('esnad.consent_purposes') as $purpose)
        @php($required = $purpose === 'service' || ($purpose === 'transfer_abroad' && $transferRequired))
        @php($field = 'consent_'.$purpose)
        <label class="flex gap-3 items-start p-4 rounded-lg border {{ $errors->has($field) ? 'border-error' : 'border-n-400' }} cursor-pointer has-[:checked]:border-accent">
            <input type="checkbox" name="{{ $field }}" value="1" class="checkbox" @checked(old($field)) @if ($errors->has($field)) aria-invalid="true" aria-describedby="{{ $field }}-error" @endif>
            <span>
                <span class="font-medium">{{ __("consent.purposes.$purpose.label") }}</span>
                <span class="badge ms-1 align-middle {{ $required ? 'badge-accent' : '' }}">{{ $required ? __('consent.required') : __('consent.optional') }}</span>
                <span class="block text-ink-2 text-[15px] mt-1">{{ __("consent.purposes.$purpose.text") }}</span>
                @error($field)<span id="{{ $field }}-error" class="field-error block">{{ $message }}</span>@enderror
            </span>
        </label>
    @endforeach
</fieldset>
