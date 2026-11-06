<?php

namespace App\Filament\Resources\JenisRemisis\Pages;

use App\Filament\Resources\JenisRemisis\JenisRemisiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJenisRemisis extends ListRecords
{
    protected static string $resource = JenisRemisiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
