<?php

namespace App\Filament\Pages;

use App\Services\InstallCheck;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** The /install/check results inside the admin (section 13, System). */
class InstallCheckPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $slug = 'install-check';

    protected string $view = 'filament.pages.install-check';

    public array $checks = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('install.view');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.groups.system');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.install.title');
    }

    public function getTitle(): string
    {
        return __('admin.install.title');
    }

    public function mount(InstallCheck $checker): void
    {
        $this->checks = $checker->run(network: false);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('network')->label(__('admin.run_network'))->action(fn (InstallCheck $checker) => $this->checks = $checker->run(network: true)),
        ];
    }
}
