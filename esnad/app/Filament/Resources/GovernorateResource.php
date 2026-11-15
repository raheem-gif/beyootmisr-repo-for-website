<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\GovernorateResource\Pages\ManageGovernorates;
use App\Models\Governorate;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class GovernorateResource extends Resource
{
    use GuardedByPermission;

    protected static ?string $model = Governorate::class;

    protected static string $viewPermission = 'governorates.manage';

    protected static string $managePermission = 'governorates.manage';

    protected static bool $readOnly = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.groups.content');
    }

    public static function getModelLabel(): string
    {
        return __('admin.governorates.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.governorates.plural');
    }

    public static function canCreate(): bool
    {
        return false; // The 27 governorates are fixed.
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name_ar')->label(__('admin.fields.name_ar'))->required()->maxLength(80),
            TextInput::make('name_en')->label(__('admin.fields.name_en'))->required()->maxLength(80),
            TextInput::make('slug')->label(__('admin.fields.slug'))->required()->alphaDash()->maxLength(80),
            TextInput::make('sort')->label(__('admin.fields.sort'))->numeric()->required(),
            Toggle::make('is_active')->label(__('admin.fields.is_active')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('sort')->label('#')->sortable(),
                TextColumn::make('name_ar')->label(__('admin.fields.name_ar'))->searchable(),
                TextColumn::make('name_en')->label(__('admin.fields.name_en'))->searchable(),
                TextColumn::make('id_code')->label(__('admin.fields.id_code')),
                ToggleColumn::make('is_active')->label(__('admin.fields.is_active'))
                    ->disabled(fn () => ! auth()->user()?->can('governorates.manage')),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageGovernorates::route('/')];
    }
}
