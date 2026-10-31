<?php

namespace App\Filament\Resources\KalenderAkademiks\Pages;

use App\Filament\Resources\KalenderAkademiks\KalenderAkademikResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKalenderAkademik extends EditRecord
{
    protected static string $resource = KalenderAkademikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
