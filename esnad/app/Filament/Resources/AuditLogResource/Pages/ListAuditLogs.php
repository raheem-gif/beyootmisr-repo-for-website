<?php

namespace App\Filament\Resources\AuditLogResource\Pages;

use App\Filament\Resources\AuditLogResource;
use App\Services\Audit;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('verify')->label('SHA-256')->icon('heroicon-o-link')->action(function () {
                $broken = Audit::verifyChain();
                $broken === null
                    ? Notification::make()->success()->title(__('admin.chain_ok'))->send()
                    : Notification::make()->danger()->title(__('admin.chain_broken', ['id' => $broken]))->send();
            }),
        ];
    }
}
