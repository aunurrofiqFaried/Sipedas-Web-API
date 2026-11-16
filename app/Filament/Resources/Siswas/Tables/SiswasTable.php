<?php

namespace App\Filament\Resources\Siswas\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Models\Kelas;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;
use App\Models\KelasSiswa;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Collection;
use App\Filament\Imports\SiswaImporter;
use Filament\Actions\ImportAction;
use App\Filament\Resources\Siswas\SiswaResource;
use App\Models\Siswa;

class SiswasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
            TextColumn::make('nis')
                ->label('NIS')
                ->searchable()
                ->sortable(),

            TextColumn::make('nama')
                ->searchable()
                ->sortable(),

            TextColumn::make('jenis_kelamin')
                ->label('L/P'),

            TextColumn::make('agama')
            ->toggleable(),

            TextColumn::make('kelasSiswaAktif.kelas.nama')
                ->label('Kelas')
                ->placeholder('-'),

            TextColumn::make('nama_wali')
                ->label('Wali')
                ->searchable()
                ->toggleable(),

            IconColumn::make('no_hp_wali')
                ->label('No. HP')
                ->boolean()
                ->getStateUsing(fn ($record) => filled($record->no_hp_wali))
                ->toggleable(),

            TextColumn::make('status')
                ->badge()
                ->formatStateUsing(fn (?string $state) => Siswa::STATUS[$state] ?? $state)
                ->color(fn (?string $state) => match ($state) {
                    Siswa::STATUS_AKTIF => 'success',
                    Siswa::STATUS_LULUS => 'info',
                    Siswa::STATUS_PINDAH => 'warning',
                    default => 'danger',
                })
                ->description(fn (Siswa $record) => $record->status === Siswa::STATUS_AKTIF
                    ? null
                    : $record->tanggal_keluar?->translatedFormat('j M Y')),
        ])
        ->filters([
            // Bawaan: hanya siswa aktif. Pilih status lain untuk melihat lulusan, yang pindah, atau yang keluar.
            SelectFilter::make('status')
                ->options(Siswa::STATUS)
                ->default(Siswa::STATUS_AKTIF),

            SelectFilter::make('kelas')
                ->label('Kelas (tahun ajaran aktif)')
                ->options(fn () => Kelas::whereHas('tahunAjaran', fn ($q) => $q->where('is_active', true))
                    ->orderBy('nama')
                    ->pluck('nama', 'id'))
                ->query(fn (Builder $query, array $data) => $query->when(
                    $data['value'],
                    fn (Builder $q, $kelasId) => $q->whereHas(
                        'kelasSiswaAktif',
                        fn (Builder $k) => $k->where('kelas_id', $kelasId)
                    )
                )),
            SelectFilter::make('agama')
                ->options(array_combine(Siswa::AGAMA, Siswa::AGAMA)),

            TernaryFilter::make('punya_hp_wali')
                ->label('Nomor wali')
                ->placeholder('Semua')
                ->trueLabel('Sudah diisi')
                ->falseLabel('Belum diisi')
                ->queries(
                    true: fn (Builder $q) => $q->whereNotNull('no_hp_wali')->where('no_hp_wali', '!=', ''),
                    false: fn (Builder $q) => $q->where(fn ($w) => $w->whereNull('no_hp_wali')->orWhere('no_hp_wali', '')),
                    blank: fn (Builder $q) => $q,
                ),

            TernaryFilter::make('tanpa_kelas')
                ->label('Status kelas')
                ->placeholder('Semua')
                ->trueLabel('Belum punya kelas')
                ->falseLabel('Sudah punya kelas')
                ->queries(
                    true: fn (Builder $q) => $q->whereDoesntHave('kelasSiswaAktif'),
                    false: fn (Builder $q) => $q->whereHas('kelasSiswaAktif'),
                    blank: fn (Builder $q) => $q,
                ),
        ])
        ->headerActions([
            ImportAction::make()
                ->label('Import dari CSV')
                ->importer(SiswaImporter::class)
                ->visible(fn (): bool => SiswaResource::canCreate()),
        ])
        ->defaultSort('nama')
        ->defaultPaginationPageOption(25)
            ->recordActions([
                EditAction::make()
                ->visible(fn (): bool => SiswaResource::canCreate()),
            ])
        ->toolbarActions([
            BulkActionGroup::make([
                BulkAction::make('masukkan_kelas')
                    ->label('Masukkan ke kelas')
                    ->icon('heroicon-o-academic-cap')
                    ->visible(fn (): bool => SiswaResource::canCreate())
                    ->schema([
                        Select::make('kelas_id')
                            ->label('Kelas (tahun ajaran aktif)')
                            ->options(fn () => Kelas::whereHas(
                                'tahunAjaran',
                                fn ($q) => $q->where('is_active', true)
                            )->orderBy('nama')->pluck('nama', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $kelas = Kelas::findOrFail($data['kelas_id']);

                        foreach ($records as $siswa) {
                            KelasSiswa::updateOrCreate(
                                [
                                    'tahun_ajaran_id' => $kelas->tahun_ajaran_id,
                                    'siswa_id' => $siswa->id,
                                ],
                                ['kelas_id' => $kelas->id]
                            );
                        }

                        Notification::make()
                            ->title($records->count() . ' siswa dimasukkan ke ' . $kelas->nama)
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),

                BulkAction::make('ubah_status')
                    ->label('Ubah status (lulus, pindah, keluar)')
                    ->icon('heroicon-o-arrow-right-start-on-rectangle')
                    ->color('warning')
                    ->visible(fn (): bool => SiswaResource::canCreate())
                    ->requiresConfirmation()
                    ->modalDescription('Riwayat absensi, pelanggaran, dan SP siswa tetap tersimpan. Siswa hanya tidak muncul lagi di absensi.')
                    ->schema([
                        Select::make('status')
                            ->options(Siswa::STATUS)
                            ->default(Siswa::STATUS_LULUS)
                            ->required()
                            ->live()
                            ->native(false),

                        DatePicker::make('tanggal_keluar')
                            ->label('Tanggal')
                            ->default(now())
                            ->required(fn (Get $get) => $get('status') !== Siswa::STATUS_AKTIF)
                            ->visible(fn (Get $get) => $get('status') !== Siswa::STATUS_AKTIF),

                        TextInput::make('keterangan_keluar')
                            ->label('Keterangan (opsional)')
                            ->maxLength(255)
                            ->visible(fn (Get $get) => $get('status') !== Siswa::STATUS_AKTIF),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $aktif = $data['status'] === Siswa::STATUS_AKTIF;

                        foreach ($records as $siswa) {
                            // is_active mengikuti status secara otomatis (lihat model Siswa)
                            $siswa->update([
                                'status' => $data['status'],
                                'tanggal_keluar' => $aktif ? null : $data['tanggal_keluar'],
                                'keterangan_keluar' => $aktif ? null : ($data['keterangan_keluar'] ?? null),
                            ]);
                        }

                        Notification::make()
                            ->title($records->count() . ' siswa diubah menjadi ' . strtolower(Siswa::STATUS[$data['status']]))
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]),
        ]);
    }
}