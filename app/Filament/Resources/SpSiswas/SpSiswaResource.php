<?php

namespace App\Filament\Resources\SpSiswas;

use App\Filament\Resources\SpSiswas\Pages\ManageSpSiswas;
use App\Models\SpSiswa;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class SpSiswaResource extends Resource
{
    protected static ?string $model = SpSiswa::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'Surat Peringatan';
    protected static ?string $pluralModelLabel = 'Surat Peringatan';
    protected static ?string $navigationLabel = 'Surat Peringatan (SP)';
    protected static string|UnitEnum|null $navigationGroup = 'Kesiswaan';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && in_array($user->role, [
                User::ROLE_ADMIN,
                User::ROLE_KESISWAAN,
                User::ROLE_KEPALA_SEKOLAH,
            ], true);
    }

    // SP dibuat oleh sistem, bukan diketik atau dihapus manual
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    // Angka merah di menu: jumlah SP yang menunggu keputusan
    public static function getNavigationBadge(): ?string
    {
        $ta = TahunAjaran::aktif();

        $jumlah = $ta
            ? SpSiswa::where('tahun_ajaran_id', $ta->id)->where('status', 'menunggu')->count()
            : 0;

        return $jumlah > 0 ? (string) $jumlah : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    private static function bolehMemutuskan(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && in_array($user->role, [User::ROLE_ADMIN, User::ROLE_KESISWAAN], true);
    }
    public static function table(Table $table): Table
    {
    return $table
        ->modifyQueryUsing(fn (Builder $query) => $query
            ->where('tahun_ajaran_id', TahunAjaran::aktif()?->id)
            ->with(['siswa.kelasSiswaAktif.kelas', 'aturan', 'penyetuju']))
        ->columns([
            TextColumn::make('siswa.nama')
                ->label('Siswa')
                ->searchable(),

            TextColumn::make('siswa.kelasSiswaAktif.kelas.nama')
                ->label('Kelas')
                ->placeholder('-'),

            TextColumn::make('aturan.nama')
                ->label('Jenis SP')
                ->badge()
                ->color(fn (?string $state): string => $state === 'SP1' ? 'warning' : 'danger'),

            TextColumn::make('total_poin_saat_terbit')
                ->label('Total poin'),

            TextColumn::make('poin_sekarang')
            ->label('Poin sekarang')
            ->state(fn (SpSiswa $r): int => $r->siswa->totalPoin($r->tahun_ajaran_id)),

            TextColumn::make('status')
                ->badge()
                ->formatStateUsing(fn (string $state): string => ucfirst($state))
                ->color(fn (string $state): string => match ($state) {
                    'menunggu' => 'warning',
                    'disetujui' => 'success',
                    default => 'gray',
                }),

            TextColumn::make('tanggal_terbit')
                ->label('Tanggal terbit')
                ->date('d M Y')
                ->placeholder('-'),

            TextColumn::make('penyetuju.name')
                ->label('Diputuskan oleh')
                ->placeholder('-')
                ->toggleable(),

            TextColumn::make('catatan')
                ->limit(40)
                ->placeholder('-')
                ->toggleable(),

            TextColumn::make('created_at')
                ->label('Diusulkan')
                ->dateTime('d M Y H:i')
                ->sortable(),
        ])
        ->filters([
            SelectFilter::make('status')
                ->options([
                    'menunggu' => 'Menunggu',
                    'disetujui' => 'Disetujui',
                    'ditolak' => 'Ditolak',
                ])
                ->default('menunggu'),

            SelectFilter::make('aturan_sp_id')
                ->label('Jenis SP')
                ->relationship('aturan', 'nama'),
        ])
        ->defaultSort('created_at', 'desc')
        ->recordActions([
            Action::make('setujui')
                ->label('Setujui')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (SpSiswa $record): bool => $record->status === 'menunggu' && self::bolehMemutuskan())
                ->modalHeading('Setujui SP ini?')
                ->modalDescription(fn (SpSiswa $record): string => "{$record->siswa->nama} akan menerima {$record->aturan->nama}.")
                ->schema([
                    Textarea::make('catatan')
                        ->label('Catatan (opsional)')
                        ->maxLength(1000),
                ])
                ->action(function (SpSiswa $record, array $data): void {
                    $record->refresh();

                    if ($record->status !== 'menunggu') {
                        Notification::make()->title('SP ini sudah diputuskan oleh orang lain')->warning()->send();

                        return;
                    }

                    $record->update([
                        'status' => 'disetujui',
                        'tanggal_terbit' => now()->toDateString(),
                        'disetujui_oleh' => auth()->id(),
                        'catatan' => $data['catatan'] ?? null,
                    ]);

                    Notification::make()->title('SP disetujui')->success()->send();
                }),

            Action::make('tolak')
                ->label('Tolak')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (SpSiswa $record): bool => $record->status === 'menunggu' && self::bolehMemutuskan())
                ->modalHeading('Tolak usulan SP ini?')
                ->schema([
                    Textarea::make('catatan')
                        ->label('Alasan penolakan')
                        ->required()
                        ->maxLength(1000),
                ])
                ->action(function (SpSiswa $record, array $data): void {
                    $record->refresh();

                    if ($record->status !== 'menunggu') {
                        Notification::make()->title('SP ini sudah diputuskan oleh orang lain')->warning()->send();

                        return;
                    }

                    $record->update([
                        'status' => 'ditolak',
                        'disetujui_oleh' => auth()->id(),
                        'catatan' => $data['catatan'],
                    ]);

                    Notification::make()->title('Usulan SP ditolak')->success()->send();
                }),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSpSiswas::route('/'),
        ];
    }
}
