<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\ConsentResource\Pages\ListConsents;
use App\Models\Consent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Read-only consent register (section 4.4). */
class ConsentResource extends Resource
{
    use GuardedByPermission;

    protected static ?string $model = Consent::class;

    protected static string $viewPermission = 'consents.view';

    protected static string $managePermission = 'consents.view';

    protected static bool $readOnly = true;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.groups.data_protection');
    }

    public static function getModelLabel(): string
    {
        return __('admin.consents.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.consents.plural');
    }

    public static function table(Table $table): Table
    {
        $purposes = collect(config('esnad.consent_purposes'))->mapWithKeys(fn ($p) => [$p => __("consent.purposes.$p.label")])->all();

        return $table
            ->defaultSort('given_at', 'desc')
            ->columns([
                TextColumn::make('user.ulid')->label(__('admin.fields.user'))->searchable()->copyable(),
                TextColumn::make('purpose')->label(__('admin.fields.purpose'))->formatStateUsing(fn ($state) => $purposes[$state] ?? $state)->wrap(),
                TextColumn::make('version')->label(__('admin.fields.version')),
                TextColumn::make('method')->label(__('admin.fields.method'))->formatStateUsing(fn ($state) => __("consent.method.$state")),
                TextColumn::make('scope')->label(__('admin.fields.scope'))->placeholder('-'),
                TextColumn::make('given_at')->label(__('admin.fields.given_at'))->dateTime()->sortable(),
                TextColumn::make('withdrawn_at')->label(__('admin.fields.withdrawn_at'))->dateTime()->placeholder('-')->sortable(),
                TextColumn::make('ip')->label(__('admin.fields.ip'))->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('purpose')->label(__('admin.fields.purpose'))->options($purposes),
                TernaryFilter::make('withdrawn_at')->label(__('admin.fields.withdrawn_at'))->nullable(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListConsents::route('/')];
    }
}
