<?php

namespace App\Filament\Widgets;

use App\Models\AturanSp;
use App\Models\PelanggaranSiswa;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class SiswaMendekatiSp extends TableWidget
{
    // Siswa dengan total poin minimal segini akan muncul di daftar
    private const AMBANG_POIN = 20;

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && in_array($user->role, [
                User::ROLE_ADMIN,
                User::ROLE_KESISWAAN,
                User::ROLE_KEPALA_SEKOLAH,
            ], true);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Siswa Mendekati Batas SP')
            ->description('Total poin pelanggaran tahun ajaran aktif, minimal ' . self::AMBANG_POIN . ' poin.')
            ->query(function (): Builder {
                $ta = TahunAjaran::aktif();

                if (! $ta) {
                    return Siswa::query()->whereRaw('1 = 0');
                }

                // Poin aktif = total pelanggaran dikurangi total remisi pada tahun ajaran aktif
                $net = '(COALESCE((SELECT SUM(poin) FROM pelanggaran_siswa WHERE pelanggaran_siswa.siswa_id = siswa.id AND pelanggaran_siswa.tahun_ajaran_id = ?), 0)'
                    . ' - COALESCE((SELECT SUM(poin) FROM remisi_siswa WHERE remisi_siswa.siswa_id = siswa.id AND remisi_siswa.tahun_ajaran_id = ?), 0))';

                return Siswa::query()
                    ->select('siswa.*')
                    ->selectRaw("GREATEST(0, {$net}) as total_poin", [$ta->id, $ta->id])
                    ->where('siswa.is_active', true)
                    ->whereRaw("{$net} >= ?", [$ta->id, $ta->id, self::AMBANG_POIN])
                    ->with('kelasSiswaAktif.kelas');
            })
            ->columns([
                TextColumn::make('nama')
                    ->label('Siswa')
                    ->searchable(),

                TextColumn::make('kelasSiswaAktif.kelas.nama')
                    ->label('Kelas')
                    ->placeholder('-'),

                TextColumn::make('total_poin')
                    ->label('Total poin')
                    ->badge()
                    ->color('warning')
                    ->sortable(),

                TextColumn::make('sp_berikutnya')
                    ->label('SP berikutnya')
                    ->state(function (Siswa $record): string {
                        $total = (int) $record->total_poin;

                        $berikut = AturanSp::where('batas_poin', '>', $total)
                            ->orderBy('batas_poin')
                            ->first();

                        if (! $berikut) {
                            return 'Sudah melewati batas SP tertinggi';
                        }

                        return $berikut->nama . ' (kurang ' . ($berikut->batas_poin - $total) . ' poin)';
                    }),
            ])
            ->defaultSort('total_poin', 'desc')
            ->paginated([5, 10])
            ->emptyStateHeading('Belum ada siswa yang mendekati batas SP');
    }
}