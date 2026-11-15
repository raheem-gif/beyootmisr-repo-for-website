<x-layouts.app :title="__('auth.welcome_title')" :noindex="true">
    <section class="container-page py-12 md:py-16">
        <div class="max-w-xl mx-auto">
            <h1 class="text-3xl font-semibold">{{ __('auth.welcome_title') }}</h1>
            <p class="text-ink-2 mt-2">{{ __('auth.welcome_sub') }}</p>

            <form method="post" action="{{ route('auth.welcome.store') }}" class="mt-8 space-y-6" novalidate>
                @csrf
                <x-field name="name" :label="__('auth.name_label')" :value="$user->name_ar ?? $user->name_en" autocomplete="name" required />

                <fieldset>
                    <legend class="field-label">{{ __('auth.address_form_label') }}</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach (['m' => 'address_form_m', 'f' => 'address_form_f', '' => 'address_form_none'] as $value => $label)
                            <label class="inline-flex items-center gap-2 min-h-11 px-4 rounded-md border border-n-500 cursor-pointer has-[:checked]:border-accent has-[:checked]:bg-accent-soft">
                                <input type="radio" name="address_form" value="{{ $value }}" class="checkbox !mt-0" @checked(old('address_form', $user->address_form ?? '') === $value)>
                                {{ __("auth.$label") }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div>
                    <label for="governorate_id" class="field-label">{{ __('auth.governorate_label') }}</label>
                    <select id="governorate_id" name="governorate_id" class="input" required @error('governorate_id') aria-invalid="true" @enderror>
                        <option value="">{{ __('auth.choose') }}</option>
                        @foreach ($governorates as $g)
                            <option value="{{ $g->id }}" @selected(old('governorate_id', $user->governorate_id) == $g->id)>{{ $g->name() }}</option>
                        @endforeach
                    </select>
                    @error('governorate_id')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                @include('partials.consents', ['transferRequired' => $transferRequired])

                <button type="submit" class="btn btn-primary w-full">{{ __('auth.save_continue') }}</button>
            </form>
        </div>
    </section>
</x-layouts.app>
