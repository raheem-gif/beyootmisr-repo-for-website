<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\AccessLogResource\Pages\ListAccessLogs;
use App\Models\AccessLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Anti-Cybercrime Law logs. Disclosure only on a reasoned judicial order (phase 8 adds the export with second approval). */
class AccessLogResource extends Resource
{
    use GuardedByPermission;

    protected static ?string $model = AccessLog::class;

    protected static string $viewPermission = 'access_logs.view';

    protected static string $managePermission = 'access_logs.view';

    protected static bool $readOnly = true;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.groups.data_protection');
    }

    public static function getModelLabel(): string
    {
        return __('admin.access_logs.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.access_logs.plural');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label(__('admin.fields.created_at'))->dateTime()->sortable(),
                TextColumn::make('user_id')->label(__('admin.fields.user'))->placeholder('-'),
                TextColumn::make('ip')->label(__('admin.fields.ip'))->searchable(),
                TextColumn::make('method')->label('HTTP'),
                TextColumn::make('route')->label(__('admin.fields.route'))->searchable()->limit(40),
                TextColumn::make('status')->label(__('admin.fields.http_status')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAccessLogs::route('/')];
    }
}
