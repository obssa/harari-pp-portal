<?php

namespace App\Filament\Pages;

use App\Domain\Events\Actions\CheckInRegistration;
use App\Domain\Events\Enums\RegistrationStatus;
use App\Domain\Events\Models\EventRegistration;
use App\Filament\Support\CurrentUser;
use App\Models\User;
use App\Services\Calendar\EthiopianCalendar;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * The door (ADR-0025): scanning a ticket's QR code opens this page with the
 * ticket filled in; staff see who it is and let them in with one tap. A
 * code can also be typed or pasted.
 *
 * @property-read Schema $form
 */
class CheckIn extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?string $slug = 'check-in';

    protected static ?int $navigationSort = 21;

    #[Url]
    public string $code = '';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.content');
    }

    public static function getNavigationLabel(): string
    {
        return __('events.check_in.title');
    }

    public function getTitle(): string
    {
        return __('events.check_in.title');
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->can('events.registrations');
    }

    public function mount(): void
    {
        $this->form->fill(['code' => $this->code]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->label(__('events.check_in.code'))
                ->helperText(__('events.check_in.code_help'))
                ->autofocus()
                ->autocomplete(false)
                ->live(debounce: 400)
                ->afterStateUpdated(fn (?string $state) => $this->code = trim((string) $state)),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        $registration = $this->registration();

        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])->id('form')->livewireSubmitHandler('checkIn'),
            Section::make(__('events.check_in.ticket'))
                ->visible(fn (): bool => $this->code !== '')
                ->schema($registration === null
                    ? [TextEntry::make('missing')->hiddenLabel()->state(__('events.check_in.unknown'))->color('danger')]
                    : [
                        TextEntry::make('name')->label(__('events.registration.field.name'))->state($registration->name)->size(TextSize::Large)->weight(FontWeight::Bold),
                        TextEntry::make('event')->label(__('events.admin.singular'))->state($registration->event->title()),
                        TextEntry::make('when')->label(__('events.when'))
                            ->state(app(EthiopianCalendar::class)->format($registration->event->starts_at, withTime: ! $registration->event->all_day)),
                        TextEntry::make('status')->label(__('events.registration.admin.status'))
                            ->state($registration->status->label())->badge()->color($registration->status->color()),
                        TextEntry::make('checked_in')->label(__('events.registration.admin.checked_in'))
                            ->state($registration->checked_in_at?->setTimezone(EthiopianCalendar::timezone())->format('H:i') ?? '—'),
                        Actions::make([
                            Action::make('checkIn')
                                ->label(__('events.check_in.action'))
                                ->icon(Heroicon::OutlinedCheckCircle)
                                ->size(Size::ExtraLarge)
                                ->visible($registration->status === RegistrationStatus::Registered && $registration->checked_in_at === null)
                                ->action(fn () => $this->checkIn()),
                        ]),
                    ]),
        ]);
    }

    public function checkIn(): void
    {
        abort_unless(CurrentUser::get()->can('events.registrations'), 403);

        $registration = $this->registration();
        if ($registration === null) {
            Notification::make()->danger()->title(__('events.check_in.unknown'))->send();

            return;
        }

        try {
            app(CheckInRegistration::class)->handle($registration, CurrentUser::get());
            Notification::make()->success()->title(__('events.check_in.done', ['name' => $registration->name]))->send();
        } catch (ValidationException $e) {
            Notification::make()->danger()->title((string) collect($e->errors())->flatten()->first())->send();
        }
    }

    private function registration(): ?EventRegistration
    {
        $code = trim($this->code);
        // A door scanner types the whole address from the QR code.
        if (str_contains($code, 'code=')) {
            parse_str((string) parse_url($code, PHP_URL_QUERY), $query);
            $code = is_string($query['code'] ?? null) ? $query['code'] : '';
        }
        if ($code === '') {
            return null;
        }

        $registration = EventRegistration::findByToken($code);

        return $registration !== null && $registration->event()->exists() ? $registration->load('event.translations') : null;
    }
}
