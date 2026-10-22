<?php

namespace App\Filament\Resources\Forms;

use App\Domain\Forms\Enums\FormType;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Support\FormField;
use App\Domain\Organisation\Models\Office;
use App\Domain\Translation\Support\Locales;
use App\Filament\Resources\Forms\Pages\CreateForm;
use App\Filament\Resources\Forms\Pages\EditForm;
use App\Filament\Resources\Forms\Pages\ListForms;
use App\Filament\Support\DirectoryFields;
use App\Filament\Support\Translatable;
use App\Filament\Support\ViewOnSite;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use UnitEnum;

class FormResource extends Resource
{
    protected static ?string $model = Form::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 31;

    public static function getModelLabel(): string
    {
        return __('forms.admin.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('forms.admin.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('forms.admin.nav');
    }

    public static function form(Schema $schema): Schema
    {
        $perLanguage = fn (string $name, string $label, bool $multiline = false): Fieldset => Fieldset::make($label)->columns(2)->schema(array_map(
            fn (string $locale) => ($multiline ? Textarea::make("{$name}.{$locale}")->rows(3) : TextInput::make("{$name}.{$locale}")->maxLength(200))
                ->label(Locales::nativeName($locale))->extraInputAttributes(['lang' => $locale]),
            Locales::all(),
        ));

        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 2])->schema([
                Translatable::tabs(fn (string $locale): array => [
                    Translatable::mainField($locale, 'title', 200)->label(__('admin.posts.field.title')),
                    Translatable::slugField($locale, 'form_translations'),
                    Textarea::make("translations.{$locale}.intro")->label(__('forms.admin.field.intro'))->rows(3)->maxLength(2000),
                    Textarea::make("translations.{$locale}.success_message")->label(__('forms.admin.field.success_message'))
                        ->helperText(__('forms.admin.help.success_message'))->rows(2)->maxLength(1000),
                    TextInput::make("translations.{$locale}.autoreply_subject")->label(__('forms.admin.field.autoreply_subject'))->maxLength(200),
                    Textarea::make("translations.{$locale}.autoreply_body")->label(__('forms.admin.field.autoreply_body'))
                        ->helperText(__('forms.admin.help.autoreply_body'))->rows(4)->maxLength(3000),
                    DirectoryFields::published($locale, 'forms.manage'),
                ]),
                Section::make(__('forms.admin.section.questions'))
                    ->description(__('forms.admin.help.questions'))
                    ->schema([
                        Repeater::make('fields')
                            ->hiddenLabel()
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->defaultItems(0)
                            ->maxItems(Form::MAX_FIELDS)
                            ->addActionLabel(__('forms.admin.add_question'))
                            ->itemLabel(fn (array $state): string => (string) (collect((array) ($state['label'] ?? []))->filter()->first() ?? __('forms.admin.new_question')))
                            ->schema([
                                Hidden::make('key')->default(fn (): string => Str::lower(Str::random(10))),
                                Grid::make(2)->schema([
                                    Select::make('type')->label(__('forms.admin.field.question_type'))->required()->live()->default(FormField::TEXT)
                                        ->options(collect(FormField::TYPES)->mapWithKeys(fn (string $t): array => [$t => __("forms.field_type.{$t}")])->all()),
                                    Toggle::make('required')->label(__('forms.admin.field.required'))->inline(false),
                                ]),
                                $perLanguage('label', __('forms.admin.field.question')),
                                $perLanguage('help', __('forms.admin.field.help')),
                                $perLanguage('choices', __('forms.admin.field.choices'), true)
                                    ->visible(fn (Get $get): bool => in_array($get('type'), FormField::WITH_CHOICES, true)),
                            ]),
                    ]),
            ]),
            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 1])->schema([
                Section::make(__('admin.posts.section.details'))->schema([
                    Select::make('type')->label(__('forms.admin.field.type'))->options(FormType::options())->default(FormType::Contact->value)->required(),
                    Toggle::make('active')->label(__('forms.admin.field.active'))->default(true),
                    Toggle::make('on_contact_page')->label(__('forms.admin.field.on_contact_page'))->helperText(__('forms.admin.help.on_contact_page')),
                    Toggle::make('anonymous_allowed')->label(__('forms.admin.field.anonymous_allowed'))->helperText(__('forms.admin.help.anonymous_allowed')),
                ]),
                Section::make(__('forms.admin.section.routing'))->schema([
                    Select::make('office_id')->label(__('forms.admin.field.office'))->searchable()
                        ->helperText(__('forms.admin.help.office'))
                        ->options(fn (): array => Office::query()->with('translations')->orderBy('sort')->get()
                            ->mapWithKeys(fn (Office $o): array => [$o->id => $o->name()])->all()),
                    TextInput::make('notify_email')->label(__('forms.admin.field.notify_email'))->email()->maxLength(190)
                        ->helperText(__('forms.admin.help.notify_email')),
                    TextInput::make('response_days')->label(__('forms.admin.field.response_days'))->numeric()->integer()
                        ->minValue(1)->maxValue(30)->default(3)->helperText(__('forms.admin.help.response_days')),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('translations')->withCount('submissions'))
            ->columns([
                TextColumn::make('title')->label(__('admin.posts.field.title'))->state(fn (Form $record): string => $record->title())->wrap(),
                TextColumn::make('type')->label(__('forms.admin.field.type'))->badge()->formatStateUsing(fn (FormType $state): string => $state->label()),
                IconColumn::make('active')->label(__('forms.admin.field.active'))->boolean(),
                IconColumn::make('on_contact_page')->label(__('forms.admin.field.on_contact_page'))->boolean(),
                TextColumn::make('submissions_count')->label(__('forms.admin.field.submissions'))->numeric(),
                DirectoryFields::languagesColumn(),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([ViewOnSite::row(), EditAction::make(), DeleteAction::make(), RestoreAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListForms::route('/'),
            'create' => CreateForm::route('/create'),
            'edit' => EditForm::route('/{record}/edit'),
        ];
    }

    /**
     * @return Builder<Form>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Form> */
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
