<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\SettingResource\Pages\ManageSettings;
use App\Models\Setting;
use App\Services\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Admin-editable values. Each change is versioned with who, when and why. */
class SettingResource extends Resource
{
    use GuardedByPermission;

    protected static ?string $model = Setting::class;

    protected static string $viewPermission = 'settings.manage';

    protected static string $managePermission = 'settings.manage';

    protected static bool $readOnly = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.groups.system');
    }

    public static function getModelLabel(): string
    {
        return __('admin.settings.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.settings.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('key')
            ->columns([
                TextColumn::make('key')->label(__('admin.fields.key'))->searchable()->fontFamily('mono'),
                TextColumn::make('value')->label(__('admin.fields.value'))->formatStateUsing(fn ($state) => json_encode($state, JSON_UNESCAPED_UNICODE))->fontFamily('mono'),
                TextColumn::make('version')->label(__('admin.fields.version')),
                TextColumn::make('updated_at')->label(__('admin.fields.created_at'))->dateTime()->sortable(),
            ])
            ->recordActions([
                Action::make('change')
                    ->label(__('filament-actions::edit.single.label'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn () => auth()->user()?->can('settings.manage'))
                    ->fillForm(fn (Setting $record) => ['value' => json_encode($record->value, JSON_UNESCAPED_UNICODE)])
                    ->schema([
                        Textarea::make('value')->label(__('admin.fields.value'))->required()->rows(3)
                            ->rule(fn () => function ($attribute, $value, $fail) {
                                json_decode((string) $value);
                                if (json_last_error() !== JSON_ERROR_NONE) {
                                    $fail(__('admin.invalid_json'));
                                }
                            }),
                        TextInput::make('reason')->label(__('admin.fields.reason'))->required()->maxLength(255)->helperText(__('admin.reason_required')),
                    ])
                    ->action(fn (Setting $record, array $data) => Settings::set($record->key, json_decode($data['value'], true), $data['reason'])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSettings::route('/')];
    }
}
