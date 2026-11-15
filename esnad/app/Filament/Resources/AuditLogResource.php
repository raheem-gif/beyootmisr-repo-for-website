<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\AuditLogResource\Pages\ListAuditLogs;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Append-only audit log. Nothing here can be edited or deleted. */
class AuditLogResource extends Resource
{
    use GuardedByPermission;

    protected static ?string $model = AuditLog::class;

    protected static string $viewPermission = 'audit.view';

    protected static string $managePermission = 'audit.view';

    protected static bool $readOnly = true;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.groups.data_protection');
    }

    public static function getModelLabel(): string
    {
        return __('admin.audit.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.audit.plural');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('created_at')->label(__('admin.fields.created_at'))->dateTime(),
            TextEntry::make('action')->label(__('admin.fields.action')),
            TextEntry::make('actor.email')->label(__('admin.fields.actor'))->placeholder('system'),
            TextEntry::make('target_type')->label(__('admin.fields.target'))->formatStateUsing(fn ($state, $record) => class_basename((string) $state).' #'.$record->target_id)->placeholder('-'),
            TextEntry::make('reason')->label(__('admin.fields.reason'))->placeholder('-'),
            TextEntry::make('ip')->label(__('admin.fields.ip'))->placeholder('-'),
            TextEntry::make('before')->label(__('admin.fields.before'))->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))->columnSpanFull()->placeholder('-')->fontFamily('mono'),
            TextEntry::make('after')->label(__('admin.fields.after'))->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))->columnSpanFull()->placeholder('-')->fontFamily('mono'),
            TextEntry::make('chain_hash')->label('SHA-256')->columnSpanFull()->fontFamily('mono'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label(__('admin.fields.created_at'))->dateTime()->sortable(),
                TextColumn::make('actor.email')->label(__('admin.fields.actor'))->placeholder('system')->searchable(),
                TextColumn::make('action')->label(__('admin.fields.action'))->badge()->searchable(),
                TextColumn::make('target_type')->label(__('admin.fields.target'))->formatStateUsing(fn ($state, $record) => class_basename((string) $state).' #'.$record->target_id)->placeholder('-'),
                TextColumn::make('reason')->label(__('admin.fields.reason'))->limit(40)->placeholder('-'),
                TextColumn::make('ip')->label(__('admin.fields.ip'))->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')->label(__('admin.fields.action'))
                    ->options(fn () => AuditLog::query()->distinct()->orderBy('action')->pluck('action', 'action')->all()),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAuditLogs::route('/')];
    }
}
