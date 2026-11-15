<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Services\Audit;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/** Opening a user's record is a staff view of personal data, so it is audited. */
class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        Audit::viewed($this->getRecord(), 'admin.users.view');
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
