<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Models\Page;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PageResource extends Resource
{
    use GuardedByPermission;

    protected static ?string $model = Page::class;

    protected static string $viewPermission = 'pages.manage';

    protected static string $managePermission = 'pages.manage';

    protected static bool $readOnly = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.groups.content');
    }

    public static function getModelLabel(): string
    {
        return __('admin.pages.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.pages.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        $toolbar = [['bold', 'link'], ['h2', 'h3'], ['bulletList', 'orderedList'], ['undo', 'redo']];

        return $schema->columns(1)->components([
            Section::make()->columns(2)->schema([
                TextInput::make('title_ar')->label(__('admin.fields.title_ar'))->required()->maxLength(160),
                TextInput::make('title_en')->label(__('admin.fields.title_en'))->required()->maxLength(160),
                TextInput::make('version')->label(__('admin.fields.version'))->maxLength(20),
                Toggle::make('is_published')->label(__('admin.fields.is_published'))->inline(false),
                DatePicker::make('counsel_reviewed_on')->label(__('admin.fields.counsel_reviewed_on')),
                TextInput::make('counsel_name')->label(__('admin.fields.counsel_name'))->maxLength(120),
            ]),
            RichEditor::make('body_ar')->label(__('admin.fields.body_ar'))->required()->toolbarButtons($toolbar)->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar']),
            RichEditor::make('body_en')->label(__('admin.fields.body_en'))->required()->toolbarButtons($toolbar)->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en']),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title_ar')->label(__('admin.fields.title_ar'))->searchable(),
                TextColumn::make('slug')->label(__('admin.fields.slug')),
                TextColumn::make('version')->label(__('admin.fields.version')),
                IconColumn::make('is_published')->label(__('admin.fields.is_published'))->boolean(),
                TextColumn::make('counsel_reviewed_on')->label(__('admin.fields.counsel_reviewed_on'))->date()->placeholder(__('admin.counsel_pending')),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
