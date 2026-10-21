@use('App\Domain\Volunteers\Support\VolunteerOptions')
@php
    $field = 'mt-1 block w-full rounded-control border border-line bg-surface px-3 py-2 focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-600/30';
    $button = 'inline-flex items-center rounded-control bg-primary-600 px-5 py-2.5 font-semibold text-white hover:bg-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
    $groups = ['skills' => VolunteerOptions::skills(), 'availability' => VolunteerOptions::availability(), 'languages' => VolunteerOptions::languages()];
@endphp
<x-layouts.public :title="__('volunteers.form.title')" :description="__('volunteers.form.description')">
    <x-page-hero :title="__('volunteers.form.title')" :lead="__('volunteers.form.description')" tone="plaster" :crumbs="[[__('nav.get_involved'), route('get-involved')]]" />
    <div class="mx-auto max-w-page px-4 pt-2 pb-12 sm:px-6">
        <div class="max-w-2xl">

            @if ($errors->any())
                <p role="alert" class="mt-6 rounded-card border border-danger-600/40 bg-danger-50 px-4 py-3 text-danger-800">{{ __('volunteers.form.check') }}</p>
            @endif

            <form method="post" action="{{ route('volunteer.store') }}" class="mt-8 space-y-6" novalidate>
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="name" class="font-medium">{{ __('membership.field.name') }}</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="120" autocomplete="name"
                               @error('name') aria-invalid="true" aria-describedby="name-error" @enderror class="{{ $field }}">
                        @error('name')<p id="name-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="phone" class="font-medium">{{ __('membership.field.phone') }}</label>
                        <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" required maxlength="20" autocomplete="tel" inputmode="tel" dir="ltr"
                               @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror class="{{ $field }}">
                        @error('phone')<p id="phone-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="email" class="font-medium">{{ __('membership.field.email') }}</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="190" autocomplete="email" dir="ltr"
                               @error('email') aria-invalid="true" aria-describedby="email-error" @enderror class="{{ $field }}">
                        @error('email')<p id="email-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="woreda_id" class="font-medium">{{ __('membership.field.woreda_id') }}</label>
                        <select id="woreda_id" name="woreda_id" @error('woreda_id') aria-invalid="true" aria-describedby="woreda_id-error" @enderror class="{{ $field }}">
                            <option value="">{{ $woredas->isEmpty() ? __('membership.none_listed') : '—' }}</option>
                            @foreach ($woredas as $id => $label)
                                <option value="{{ $id }}" @selected(old('woreda_id') === $id)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('woreda_id')<p id="woreda_id-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                @foreach ($groups as $group => $choices)
                    <fieldset @error($group) aria-describedby="{{ $group }}-error" @enderror>
                        <legend class="font-medium">{{ __("volunteers.form.{$group}") }}</legend>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            @foreach ($choices as $key => $label)
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="{{ $group }}[]" value="{{ $key }}" @checked(in_array($key, (array) old($group, []), true)) class="size-5 rounded border-line text-primary-600">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        @error($group)<p id="{{ $group }}-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                    </fieldset>
                @endforeach

                <div>
                    <label for="message" class="font-medium">{{ __('volunteers.form.message') }}</label>
                    <textarea id="message" name="message" rows="4" maxlength="1000" aria-describedby="message-help" class="{{ $field }}">{{ old('message') }}</textarea>
                    <p id="message-help" class="mt-1 text-sm text-ink-muted">{{ __('volunteers.form.message_help') }}</p>
                    @error('message')<p class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="consent" value="1" @checked(old('consent')) required class="mt-1 size-5 rounded border-line text-primary-600"
                               @error('consent') aria-invalid="true" aria-describedby="consent-error" @enderror>
                        <span>{{ __('volunteers.form.consent') }} <a href="{{ route('page.show', ['path' => 'privacy']) }}" class="text-link underline underline-offset-4">{{ __('membership.privacy_link') }}</a></span>
                    </label>
                    @error('consent')<p id="consent-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                </div>

                <x-forms.protection :turnstile-key="$turnstileKey" />

                <button type="submit" class="{{ $button }}">{{ __('volunteers.form.submit') }}</button>
            </form>
        </div>
    </div>
</x-layouts.public>
