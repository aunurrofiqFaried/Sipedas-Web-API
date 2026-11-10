<?php

namespace App\Filament\Resources\PengaturanSekolahs\Pages;

use App\Filament\Resources\PengaturanSekolahs\PengaturanSekolahResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePengaturanSekolahs extends ManageRecords
{
    protected static string $resource = PengaturanSekolahResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
