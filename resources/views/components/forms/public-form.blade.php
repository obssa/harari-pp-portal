@props(['form', 'translation', 'turnstileKey' => null])
@use('App\Domain\Forms\Support\FormField')
@php
    $locale = app()->getLocale();
    $field = 'mt-1 block w-full rounded-control border border-line bg-surface px-3 py-2 focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-600/30';
    $optional = $form->anonymous_allowed;
@endphp
<form method="post" action="{{ route('forms.submit', ['locale' => $translation->locale, 'slug' => $translation->slug]) }}" @if ($form->hasFileQuestions()) enctype="multipart/form-data" @endif
      {{ $attributes->class('max-w-xl space-y-5') }} novalidate>
    @csrf
    @error('form')
        <p role="alert" class="rounded-card border border-danger-600/40 bg-danger-50 px-4 py-3 text-danger-800">{{ $message }}</p>
    @enderror

    <div>
        <label for="form-name" class="font-medium">{{ __('events.registration.field.name') }}@if ($optional) <span class="font-normal text-ink-muted">({{ __('forms.optional') }})</span>@endif</label>
        <input id="form-name" name="name" type="text" value="{{ old('name') }}" maxlength="120" autocomplete="name" @unless ($optional) required @endunless
               @error('name') aria-invalid="true" aria-describedby="form-name-error" @enderror class="{{ $field }}">
        @error('name')<p id="form-name-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label for="form-email" class="font-medium">{{ __('events.registration.field.email') }}</label>
            <input id="form-email" name="email" type="email" value="{{ old('email') }}" maxlength="190" autocomplete="email" dir="ltr"
                   @error('email') aria-invalid="true" aria-describedby="form-email-error" @enderror class="{{ $field }}">
            @error('email')<p id="form-email-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="form-phone" class="font-medium">{{ __('events.registration.field.phone') }}</label>
            <input id="form-phone" name="phone" type="tel" value="{{ old('phone') }}" maxlength="20" autocomplete="tel" inputmode="tel" dir="ltr"
                   @error('phone') aria-invalid="true" aria-describedby="form-phone-error" @enderror class="{{ $field }}">
            @error('phone')<p id="form-phone-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
        </div>
    </div>
    <p class="-mt-3 text-sm text-ink-muted">{{ $optional ? __('forms.contact_optional') : __('forms.contact_help') }}</p>

    @foreach ($form->questions() as $question)
        @php
            $id = 'q-'.$question->key;
            $name = 'answers['.$question->key.']';
            $errorKey = 'answers.'.$question->key;
            $label = $question->labelFor($locale);
            $help = $question->helpFor($locale);
            $old = old($errorKey);
            $describedBy = trim(($help ? $id.'-help ' : '').($errors->has($errorKey) ? $id.'-error' : ''));
        @endphp
        <div>
            @if (in_array($question->type, [FormField::RADIO, FormField::CHECKBOXES], true))
                <fieldset @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif>
                    <legend class="font-medium">{{ $label }}@if (! $question->required) <span class="font-normal text-ink-muted">({{ __('forms.optional') }})</span>@endif</legend>
                    @if ($help)<p id="{{ $id }}-help" class="text-sm text-ink-muted">{{ $help }}</p>@endif
                    <div class="mt-2 space-y-2">
                        @foreach ($question->choicesFor($locale) as $index => $choice)
                            <label class="flex items-start gap-3">
                                @if ($question->type === FormField::RADIO)
                                    <input type="radio" name="{{ $name }}" value="{{ $index }}" @checked((string) $old === (string) $index) class="mt-1 size-5 border-line text-primary-600">
                                @else
                                    <input type="checkbox" name="{{ $name }}[]" value="{{ $index }}" @checked(in_array((string) $index, array_map('strval', (array) $old), true)) class="mt-1 size-5 rounded border-line text-primary-600">
                                @endif
                                <span>{{ $choice }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @elseif ($question->type === FormField::CONSENT)
                <div class="flex items-start gap-3">
                    <input id="{{ $id }}" name="{{ $name }}" type="checkbox" value="1" @checked($old) @if ($question->required) required @endif
                           @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif class="mt-1 size-5 rounded border-line text-primary-600">
                    <label for="{{ $id }}">{{ $label }}</label>
                </div>
                @if ($help)<p id="{{ $id }}-help" class="mt-1 text-sm text-ink-muted">{{ $help }}</p>@endif
            @else
                <label for="{{ $id }}" class="font-medium">{{ $label }}@if (! $question->required) <span class="font-normal text-ink-muted">({{ __('forms.optional') }})</span>@endif</label>
                @if ($help)<p id="{{ $id }}-help" class="text-sm text-ink-muted">{{ $help }}</p>@endif
                @switch($question->type)
                    @case(FormField::TEXTAREA)
                        <textarea id="{{ $id }}" name="{{ $name }}" rows="6" maxlength="5000" @if ($question->required) required @endif
                                  @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif @error($errorKey) aria-invalid="true" @enderror class="{{ $field }}">{{ $old }}</textarea>
                        @break
                    @case(FormField::SELECT)
                        <select id="{{ $id }}" name="{{ $name }}" @if ($question->required) required @endif
                                @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif @error($errorKey) aria-invalid="true" @enderror class="{{ $field }}">
                            <option value="">{{ __('forms.choose') }}</option>
                            @foreach ($question->choicesFor($locale) as $index => $choice)
                                <option value="{{ $index }}" @selected((string) $old === (string) $index)>{{ $choice }}</option>
                            @endforeach
                        </select>
                        @break
                    @case(FormField::FILE)
                        <input id="{{ $id }}" name="{{ $name }}" type="file" accept=".pdf,.docx,.odt,.jpg,.jpeg,.png" @if ($question->required) required @endif
                               aria-describedby="{{ trim($id.'-file '.$describedBy) }}" @error($errorKey) aria-invalid="true" @enderror class="mt-1 block w-full text-sm">
                        <p id="{{ $id }}-file" class="mt-1 text-xs text-ink-muted">{{ __('forms.file_help', ['max' => 5]) }}</p>
                        @break
                    @default
                        @php
                            $inputType = match ($question->type) {
                                FormField::EMAIL => 'email',
                                FormField::PHONE => 'tel',
                                FormField::NUMBER => 'number',
                                FormField::DATE => 'date',
                                default => 'text',
                            };
                        @endphp
                        <input id="{{ $id }}" name="{{ $name }}" type="{{ $inputType }}" value="{{ is_scalar($old) ? $old : '' }}" @if ($question->type === FormField::TEXT) maxlength="300" @endif
                               @if ($question->required) required @endif @if (in_array($question->type, [FormField::EMAIL, FormField::PHONE], true)) dir="ltr" @endif
                               @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif @error($errorKey) aria-invalid="true" @enderror class="{{ $field }}">
                @endswitch
            @endif
            @error($errorKey)<p id="{{ $id }}-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
        </div>
    @endforeach

    <x-forms.protection :turnstile-key="$turnstileKey" />

    <p class="text-sm text-ink-muted">{{ __('forms.privacy') }}</p>

    <button type="submit" class="inline-flex items-center rounded-control bg-primary-600 px-5 py-2.5 font-semibold text-white hover:bg-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">{{ __('forms.send') }}</button>
</form>
