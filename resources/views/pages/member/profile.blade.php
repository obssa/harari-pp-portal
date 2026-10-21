@use('App\Domain\Membership\Support\ApplicationOptions')
@use('App\Domain\Membership\Support\Mask')
@php
    $field = 'mt-1 block w-full rounded-control border border-line bg-surface px-3 py-2 focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-600/30';
    $button = 'inline-flex items-center rounded-control bg-primary-600 px-5 py-2.5 font-semibold text-white hover:bg-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
    $details = [
        'membership.field.name' => $member->name,
        'membership.field.sex' => ApplicationOptions::sexes()[$member->sex] ?? '—',
        'membership.field.id_type' => (ApplicationOptions::idTypes()[$member->id_type] ?? '—').($member->id_number ? ' · '.Mask::idNumber($member->id_number) : ''),
        'membership.field.phone' => $member->phone ?? '—',
        'membership.field.email' => $member->email ?? '—',
        'membership.field.woreda_id' => $member->woreda?->name() ?? '—',
        'membership.field.kebele_id' => $member->kebele?->name() ?? '—',
        'membership.field.address_note' => $member->address_note ?? '—',
        'membership.field.education' => ApplicationOptions::education()[$member->education] ?? '—',
        'membership.field.occupation' => $member->occupation ?? '—',
    ];
@endphp
<x-layouts.public :title="__('members.portal.nav.profile')">
    <div class="mx-auto max-w-page px-4 py-10 sm:px-6">
        <h1 class="heading-1">{{ __('members.portal.nav.profile') }}</h1>
        @include('pages.member.nav')
        @if (session('status'))
            <p role="status" class="mt-6 rounded-card border border-primary-600/40 bg-primary-50 px-4 py-3 text-primary-800 dark:bg-primary-950 dark:text-primary-200">{{ session('status') }}</p>
        @endif

        <dl class="mt-8 grid max-w-3xl gap-x-6 gap-y-3 sm:grid-cols-2">
            @foreach ($details as $label => $value)
                <div><dt class="text-sm text-ink-muted">{{ __($label) }}</dt><dd>{{ $value }}</dd></div>
            @endforeach
        </dl>

        <section class="mt-10 max-w-2xl" aria-labelledby="request-heading">
            <h2 id="request-heading" class="heading-2">{{ __('members.portal.request_title') }}</h2>
            <p class="mt-2 text-ink-muted">{{ __('members.portal.request_help') }}</p>
            <form method="post" action="{{ route('member.request') }}" class="mt-6 space-y-4" novalidate>
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach (['phone' => 'tel', 'email' => 'email'] as $name => $type)
                        <div>
                            <label for="{{ $name }}" class="font-medium">{{ __("membership.field.{$name}") }}</label>
                            <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name) }}" maxlength="190" dir="ltr"
                                   @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror class="{{ $field }}">
                            @error($name)<p id="{{ $name }}-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                    @foreach (['woreda_id' => $woredas, 'kebele_id' => $kebeles] as $name => $list)
                        <div>
                            <label for="{{ $name }}" class="font-medium">{{ __("membership.field.{$name}") }}</label>
                            <select id="{{ $name }}" name="{{ $name }}" class="{{ $field }}">
                                <option value="">{{ __('members.portal.no_change') }}</option>
                                @foreach ($list as $id => $label)
                                    <option value="{{ $id }}" @selected(old($name) === $id)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error($name)<p class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                    <div>
                        <label for="education" class="font-medium">{{ __('membership.field.education') }}</label>
                        <select id="education" name="education" class="{{ $field }}">
                            <option value="">{{ __('members.portal.no_change') }}</option>
                            @foreach ($education as $key => $label)
                                <option value="{{ $key }}" @selected(old('education') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="occupation" class="font-medium">{{ __('membership.field.occupation') }}</label>
                        <input id="occupation" name="occupation" type="text" value="{{ old('occupation') }}" maxlength="120" class="{{ $field }}">
                    </div>
                </div>
                <div>
                    <label for="address_note" class="font-medium">{{ __('membership.field.address_note') }}</label>
                    <input id="address_note" name="address_note" type="text" value="{{ old('address_note') }}" maxlength="200" class="{{ $field }}">
                </div>
                <div>
                    <label for="note" class="font-medium">{{ __('members.portal.request_note') }}</label>
                    <textarea id="note" name="note" rows="3" maxlength="1000" @error('note') aria-invalid="true" aria-describedby="note-error" @enderror class="{{ $field }}">{{ old('note') }}</textarea>
                    @error('note')<p id="note-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="{{ $button }}">{{ __('members.portal.request_send') }}</button>
            </form>
        </section>
    </div>
</x-layouts.public>
