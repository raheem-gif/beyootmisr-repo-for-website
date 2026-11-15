<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\FeatureFlagResource\Pages\ManageFeatureFlags;
use App\Models\FeatureFlag;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class FeatureFlagResource extends Resource
{
    use GuardedByPermission;

    protected static ?string $model = FeatureFlag::class;

    protected static string $viewPermission = 'feature_flags.manage';

    protected static string $managePermission = 'feature_flags.manage';

    protected static bool $readOnly = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.groups.system');
    }

    public static function getModelLabel(): string
    {
        return __('admin.flags.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.flags.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->label(__('admin.fields.key'))->fontFamily('mono'),
                TextColumn::make('description')->label(__('admin.fields.description'))->wrap(),
                ToggleColumn::make('enabled')->label(__('admin.fields.enabled')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageFeatureFlags::route('/')];
    }
}
