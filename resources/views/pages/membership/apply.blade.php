@use('App\Http\Controllers\Public\MembershipController')
@php
    $options = MembershipController::options();
    $field = 'mt-1 block w-full rounded-control border border-line bg-surface px-3 py-2 focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-600/30';
    $button = 'inline-flex items-center rounded-control bg-primary-600 px-5 py-2.5 font-semibold text-white hover:bg-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
    $secondary = 'inline-flex items-center rounded-control border border-line bg-surface px-5 py-2.5 font-semibold hover:bg-surface-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
    $steps = MembershipController::STEPS;
    $position = array_search($step, $steps, true) + 1;
    $value = fn (string $name, mixed $saved = null) => old($name, $saved);
    $birthCalendar = old('birth_calendar', app()->getLocale() === 'en' ? 'gregorian' : 'ethiopian');
    $birth = $application?->birth_date ? \Carbon\CarbonImmutable::parse($application->birth_date) : null;
    $birthEthiopian = $birth ? app(\App\Services\Calendar\EthiopianCalendar::class)->toEthiopian($birth) : null;
@endphp
<x-layouts.public :title="__('membership.apply.title')" :description="__('membership.apply.description')">
    <x-page-hero :title="__('membership.apply.title')" :lead="__('membership.apply.description')" tone="gold" :crumbs="[[__('nav.get_involved'), route('get-involved')]]" />
    <div class="mx-auto max-w-page px-4 pt-2 pb-12 sm:px-6">
        <div class="max-w-2xl">

            <ol class="mt-8 flex flex-wrap gap-2 text-sm" aria-label="{{ __('membership.apply.steps') }}">
                @foreach ($steps as $index => $name)
                    <li @if ($name === $step) aria-current="step" @endif
                        @class(['rounded-full border px-3 py-1', 'border-primary-600 bg-primary-50 font-semibold text-primary-800 dark:bg-primary-950 dark:text-primary-200' => $name === $step, 'border-line text-ink-muted' => $name !== $step])>
                        {{ __('membership.apply.step', ['number' => $index + 1, 'total' => count($steps)]) }} · {{ __("membership.step.{$name}") }}
                    </li>
                @endforeach
            </ol>

            @if (session('status'))
                <p role="status" class="mt-6 rounded-card border border-primary-600/40 bg-primary-50 px-4 py-3 text-primary-800 dark:bg-primary-950 dark:text-primary-200">{{ session('status') }}</p>
            @endif
            @error('form')
                <p role="alert" class="mt-6 rounded-card border border-danger-600/40 bg-danger-50 px-4 py-3 text-danger-800">{{ $message }}</p>
            @enderror

            @if ($step === 'about')
                <form method="post" action="{{ route('membership.about') }}" class="mt-8 space-y-6" novalidate>
                    @csrf
                    <div>
                        <label for="name" class="font-medium">{{ __('membership.field.name') }}</label>
                        <input id="name" name="name" type="text" value="{{ $value('name', $application?->name) }}" required maxlength="120" autocomplete="name"
                               @error('name') aria-invalid="true" aria-describedby="name-error" @enderror class="{{ $field }}">
                        @error('name')<p id="name-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                    </div>

                    <fieldset @error('sex') aria-describedby="sex-error" @enderror>
                        <legend class="font-medium">{{ __('membership.field.sex') }}</legend>
                        <div class="mt-2 flex flex-wrap gap-6">
                            @foreach ($options['sexes'] as $key => $label)
                                <label class="flex items-center gap-2">
                                    <input type="radio" name="sex" value="{{ $key }}" @checked($value('sex', $application?->sex) === $key) class="size-5 border-line text-primary-600">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        @error('sex')<p id="sex-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                    </fieldset>

                    <fieldset @error('birth_date') aria-describedby="birth-error" @enderror>
                        <legend class="font-medium">{{ __('membership.field.birth_date') }}</legend>
                        <div class="mt-2 flex flex-wrap gap-6 text-sm">
                            @foreach (['ethiopian', 'gregorian'] as $calendar)
                                <label class="flex items-center gap-2">
                                    <input type="radio" name="birth_calendar" value="{{ $calendar }}" @checked($birthCalendar === $calendar) class="size-5 border-line text-primary-600">
                                    {{ __("membership.calendar.{$calendar}") }}
                                </label>
                            @endforeach
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-3">
                            <div>
                                <label for="birth_day" class="text-sm">{{ __('membership.field.day') }}</label>
                                <input id="birth_day" name="birth_day" type="number" min="1" max="31" inputmode="numeric"
                                       value="{{ old('birth_day', $birthCalendar === 'ethiopian' ? $birthEthiopian?->day : $birth?->day) }}" class="{{ $field }}">
                            </div>
                            <div>
                                <label for="birth_month" class="text-sm">{{ __('membership.field.month') }}</label>
                                <select id="birth_month" name="birth_month" class="{{ $field }}">
                                    <option value="">—</option>
                                    @foreach (range(1, 13) as $month)
                                        <option value="{{ $month }}" @selected((int) old('birth_month', $birthCalendar === 'ethiopian' ? $birthEthiopian?->month : $birth?->month) === $month)>
                                            {{ $month }} · {{ __("calendar.ethiopian.month.{$month}") }}@if ($month <= 12) / {{ __("calendar.gregorian.month.{$month}") }}@endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="birth_year" class="text-sm">{{ __('membership.field.year') }}</label>
                                <input id="birth_year" name="birth_year" type="number" min="1890" max="2100" inputmode="numeric"
                                       value="{{ old('birth_year', $birthCalendar === 'ethiopian' ? $birthEthiopian?->year : $birth?->year) }}" class="{{ $field }}">
                            </div>
                        </div>
                        @error('birth_date')<p id="birth-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                    </fieldset>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="id_type" class="font-medium">{{ __('membership.field.id_type') }}</label>
                            <select id="id_type" name="id_type" @error('id_type') aria-invalid="true" aria-describedby="id_type-error" @enderror class="{{ $field }}">
                                <option value="">—</option>
                                @foreach ($options['idTypes'] as $key => $label)
                                    <option value="{{ $key }}" @selected($value('id_type', $application?->id_type) === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('id_type')<p id="id_type-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="id_number" class="font-medium">{{ __('membership.field.id_number') }}</label>
                            <input id="id_number" name="id_number" type="text" value="{{ $value('id_number', $application?->id_number) }}" maxlength="40" autocomplete="off" dir="ltr"
                                   aria-describedby="id_number-help @error('id_number') id_number-error @enderror" @error('id_number') aria-invalid="true" @enderror class="{{ $field }}">
                            <p id="id_number-help" class="mt-1 text-sm text-ink-muted">{{ __('membership.help.id_number') }}</p>
                            @error('id_number')<p id="id_number-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="education" class="font-medium">{{ __('membership.field.education') }}</label>
                            <select id="education" name="education" @error('education') aria-invalid="true" aria-describedby="education-error" @enderror class="{{ $field }}">
                                <option value="">—</option>
                                @foreach ($options['education'] as $key => $label)
                                    <option value="{{ $key }}" @selected($value('education', $application?->education) === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('education')<p id="education-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="occupation" class="font-medium">{{ __('membership.field.occupation') }}</label>
                            <input id="occupation" name="occupation" type="text" value="{{ $value('occupation', $application?->occupation) }}" maxlength="120" autocomplete="organization-title" class="{{ $field }}">
                            @error('occupation')<p class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    @if ($application === null)
                        <x-forms.protection :turnstile-key="$turnstileKey" />
                    @endif

                    <button type="submit" class="{{ $button }}">{{ __('membership.next') }}</button>
                </form>
            @elseif ($step === 'place')
                <form method="post" action="{{ route('membership.place') }}" class="mt-8 space-y-6" novalidate>
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach (['woreda_id' => $woredas, 'kebele_id' => $kebeles, 'basic_org_id' => $basicOrganisations] as $name => $list)
                            <div>
                                <label for="{{ $name }}" class="font-medium">{{ __("membership.field.{$name}") }}</label>
                                <select id="{{ $name }}" name="{{ $name }}" @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror class="{{ $field }}">
                                    <option value="">{{ $list->isEmpty() ? __('membership.none_listed') : '—' }}</option>
                                    @foreach ($list as $id => $label)
                                        <option value="{{ $id }}" @selected($value($name, $application?->{$name}) === $id)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error($name)<p id="{{ $name }}-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </div>
                    <div>
                        <label for="address_note" class="font-medium">{{ __('membership.field.address_note') }}</label>
                        <input id="address_note" name="address_note" type="text" value="{{ $value('address_note', $application?->address_note) }}" maxlength="200"
                               aria-describedby="address_note-help" class="{{ $field }}">
                        <p id="address_note-help" class="mt-1 text-sm text-ink-muted">{{ __('membership.help.address_note') }}</p>
                        @error('address_note')<p class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="phone" class="font-medium">{{ __('membership.field.phone') }}</label>
                            <input id="phone" name="phone" type="tel" value="{{ $value('phone', $application?->phone) }}" required maxlength="20" autocomplete="tel" inputmode="tel" dir="ltr"
                                   aria-describedby="phone-help @error('phone') phone-error @enderror" @error('phone') aria-invalid="true" @enderror class="{{ $field }}">
                            <p id="phone-help" class="mt-1 text-sm text-ink-muted">{{ __('membership.help.phone') }}</p>
                            @error('phone')<p id="phone-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="email" class="font-medium">{{ __('membership.field.email') }}</label>
                            <input id="email" name="email" type="email" value="{{ $value('email', $application?->email) }}" maxlength="190" autocomplete="email" dir="ltr"
                                   @error('email') aria-invalid="true" aria-describedby="email-error" @enderror class="{{ $field }}">
                            @error('email')<p id="email-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('membership.apply', ['step' => 'about']) }}" class="{{ $secondary }}">{{ __('membership.back') }}</a>
                        <button type="submit" class="{{ $button }}">{{ __('membership.next') }}</button>
                    </div>
                </form>
            @else
                <section class="mt-8 rounded-card border border-line bg-surface p-5" aria-labelledby="summary-heading">
                    <h2 id="summary-heading" class="heading-3">{{ __('membership.summary') }}</h2>
                    <dl class="mt-4 grid gap-x-6 gap-y-2 sm:grid-cols-2">
                        <div><dt class="text-sm text-ink-muted">{{ __('membership.field.name') }}</dt><dd>{{ $application->name }}</dd></div>
                        <div><dt class="text-sm text-ink-muted">{{ __('membership.field.birth_date') }}</dt><dd>@if ($birth)<x-date :date="$birth" />@endif</dd></div>
                        <div><dt class="text-sm text-ink-muted">{{ __('membership.field.phone') }}</dt><dd dir="ltr">{{ $application->phone }}</dd></div>
                        <div><dt class="text-sm text-ink-muted">{{ __('membership.field.woreda_id') }}</dt><dd>{{ $application->woreda_id ? ($woredas[$application->woreda_id] ?? '—') : ($application->address_note ?? '—') }}</dd></div>
                    </dl>
                    <p class="mt-4 text-sm"><a href="{{ route('membership.apply', ['step' => 'about']) }}" class="text-link underline-offset-4 hover:underline">{{ __('membership.change') }}</a></p>
                </section>

                <form method="post" action="{{ route('membership.code') }}" class="mt-8 space-y-5" novalidate>
                    @csrf
                    <div>
                        <div class="flex items-start gap-3">
                            <input id="consent" name="consent" type="checkbox" value="1" @checked(old('consent', $application->consented_at !== null)) required
                                   @error('consent') aria-invalid="true" aria-describedby="consent-error" @enderror class="mt-1 size-5 rounded border-line text-primary-600">
                            <label for="consent">{{ __('membership.field.consent') }} <a href="{{ route('page.show', ['path' => 'privacy']) }}" class="text-link underline-offset-4 hover:underline">{{ __('membership.privacy_link') }}</a></label>
                        </div>
                        @error('consent')<p id="consent-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="{{ $codeSent ? $secondary : $button }}">{{ $codeSent ? __('membership.resend_code') : __('membership.send_code') }}</button>
                </form>

                @if ($codeSent)
                    <form method="post" action="{{ route('membership.verify') }}" class="mt-8 max-w-sm space-y-4" novalidate>
                        @csrf
                        <div>
                            <label for="code" class="font-medium">{{ __('membership.field.code') }}</label>
                            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" dir="ltr" required
                                   aria-describedby="code-help @error('code') code-error @enderror" @error('code') aria-invalid="true" @enderror class="{{ $field }} text-lg tracking-widest">
                            <p id="code-help" class="mt-1 text-sm text-ink-muted">{{ __('membership.help.code', ['phone' => $application->phone]) }}</p>
                            @error('code')<p id="code-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="{{ $button }}">{{ __('membership.submit') }}</button>
                    </form>
                @else
                    @error('code')<p role="alert" class="mt-4 text-sm text-danger-600">{{ $message }}</p>@enderror
                @endif
            @endif

            <p class="mt-10 text-sm text-ink-muted">
                {{ __('membership.status.prompt') }}
                <a href="{{ route('membership.status') }}" class="text-link underline-offset-4 hover:underline">{{ __('membership.status.link') }}</a>
            </p>
        </div>
    </div>
</x-layouts.public>
