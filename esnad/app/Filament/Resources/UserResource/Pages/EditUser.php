<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Services\Audit;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        Audit::viewed($this->getRecord(), 'admin.users.edit');
    }

    protected function afterSave(): void
    {
        Audit::record('users.roles_synced', $this->getRecord(), null, ['roles' => $this->getRecord()->roles()->pluck('name')->all()]);
    }
}
