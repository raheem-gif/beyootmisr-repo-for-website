<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Models\User;
use App\Support\Phone;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Password;
use UnitEnum;

class UserResource extends Resource
{
    use GuardedByPermission;

    protected static ?string $model = User::class;

    protected static string $viewPermission = 'users.view';

    protected static string $managePermission = 'users.manage';

    protected static bool $readOnly = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.groups.people');
    }

    public static function getModelLabel(): string
    {
        return __('admin.users.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.users.plural');
    }

    /** Only super admins create staff accounts and change roles. */
    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->hasRole('super_admin');
    }

    public static function canEdit(Model $record): bool
    {
        $actor = auth()->user();
        if (! $actor?->can('users.manage') || $actor->getKey() === $record->getKey()) {
            return false;
        }

        // Staff accounts are edited by super admins only.
        return ! $record->isStaff() || $actor->hasRole('super_admin');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name_ar')->label(__('admin.fields.name_ar'))->maxLength(120),
            TextInput::make('name_en')->label(__('admin.fields.name_en'))->maxLength(120),
            TextInput::make('email')->label(__('admin.fields.email'))->email()->unique(ignoreRecord: true)
                ->requiredWith('password'),
            TextInput::make('phone')->label(__('admin.fields.phone'))->tel()
                ->dehydrateStateUsing(fn (?string $state) => Phone::normalise($state))
                ->rule(fn () => function ($attribute, $value, $fail) {
                    if (filled($value) && ! Phone::isValid($value)) {
                        $fail(__('auth.phone_invalid'));
                    }
                })
                ->unique(ignoreRecord: true),
            TextInput::make('password')->label(__('admin.fields.password'))->password()->revealable()
                ->rule(Password::default())
                ->dehydrated(fn (?string $state) => filled($state))
                ->required(fn (string $operation) => $operation === 'create'),
            Select::make('status')->label(__('admin.fields.status'))->options(__('admin.statuses'))->required()->default('active'),
            Select::make('roles')->label(__('admin.fields.roles'))->relationship('roles', 'name')->multiple()->preload()
                ->visible(fn () => auth()->user()?->can('roles.manage')),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name_ar')->label(__('admin.fields.name_ar'))->placeholder('-'),
            TextEntry::make('name_en')->label(__('admin.fields.name_en'))->placeholder('-'),
            TextEntry::make('phone')->label(__('admin.fields.phone'))->placeholder('-'),
            TextEntry::make('email')->label(__('admin.fields.email'))->placeholder('-'),
            TextEntry::make('status')->label(__('admin.fields.status')),
            TextEntry::make('roles.name')->label(__('admin.fields.roles'))->badge(),
            TextEntry::make('created_at')->label(__('admin.fields.created_at'))->dateTime(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('name_ar')->label(__('admin.fields.name_ar'))->searchable()->placeholder('-'),
                // Masked in the list. Opening the record shows it and writes an audit entry.
                TextColumn::make('phone')->label(__('admin.fields.phone'))->formatStateUsing(fn (?string $state) => $state ? Phone::mask($state) : null)->searchable(),
                TextColumn::make('email')->label(__('admin.fields.email'))->searchable()->placeholder('-'),
                TextColumn::make('roles.name')->label(__('admin.fields.roles'))->badge(),
                TextColumn::make('status')->label(__('admin.fields.status'))->formatStateUsing(fn ($state) => __("admin.statuses.$state")),
                TextColumn::make('created_at')->label(__('admin.fields.created_at'))->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('roles')->label(__('admin.fields.roles'))->relationship('roles', 'name'),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('roles');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
