<?php

namespace App\Filament\Resources\JenisRemisis\Pages;

use App\Filament\Resources\JenisRemisis\JenisRemisiResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJenisRemisi extends EditRecord
{
    protected static string $resource = JenisRemisiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
