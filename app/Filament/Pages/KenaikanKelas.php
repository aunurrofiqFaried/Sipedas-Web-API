<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\PembatasAkses;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\KenaikanKelasService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * @property-read Schema $form
 */
class KenaikanKelas extends Page
{
    use PembatasAkses;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedArrowTrendingUp;

    protected static ?string $navigationLabel = 'Kenaikan kelas';

    protected static ?string $title = 'Kenaikan kelas';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament-panels::pages.page';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    private ?array $cacheRencana = null;

    private ?string $kunciCache = null;

    protected static function roleEditor(): array
    {
        return [User::ROLE_KESISWAAN];
    }

    public function mount(): void
    {
        $asal = TahunAjaran::aktif() ?? TahunAjaran::orderByDesc('tanggal_mulai')->first();

        $this->form->fill([
            'tahun_asal' => $asal?->id,
            'tahun_tujuan' => $asal ? $this->tujuanDefault($asal)?->id : null,
            'tinggal' => [],
            'buat_kelas_x' => true,
            'bawa_wali' => true,
            'tanggal_lulus' => $asal?->tanggal_selesai?->toDateString(),
            'aktifkan' => true,
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        $tahun = fn () => TahunAjaran::orderByDesc('tanggal_mulai')->pluck('nama', 'id')->all();

        return $schema->components([
            Section::make('Tahun ajaran')
                ->description('Buat dulu tahun ajaran baru di menu Tahun Ajaran bila belum ada.')
                ->columns(2)
                ->schema([
                    Select::make('tahun_asal')
                        ->label('Dari tahun ajaran')
                        ->options($tahun)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set, $state) {
                            $asal = $state ? TahunAjaran::find($state) : null;
                            $set('tahun_tujuan', $asal ? $this->tujuanDefault($asal)?->id : null);
                            $set('tanggal_lulus', $asal?->tanggal_selesai?->toDateString());
                            $set('tinggal', []);
                        }),
                    Select::make('tahun_tujuan')
                        ->label('Ke tahun ajaran')
                        ->options($tahun)
                        ->required()
                        ->live(),
                ]),

            Section::make('Pengaturan')
                ->columns(2)
                ->schema([
                    Toggle::make('buat_kelas_x')
                        ->label('Buat juga kelas X kosong')
                        ->helperText('Siap diisi siswa baru lewat import CSV.')
                        ->live(),
                    Toggle::make('bawa_wali')
                        ->label('Bawa wali kelas ke kelas barunya')
                        ->helperText('Wali X TKJ 1 menjadi wali XI TKJ 1, dan seterusnya.')
                        ->live(),
                    DatePicker::make('tanggal_lulus')
                        ->label('Tanggal kelulusan')
                        ->native(false)
                        ->live(),
                    Toggle::make('aktifkan')
                        ->label('Jadikan tahun ajaran tujuan sebagai yang aktif')
                        ->default(true),
                    Select::make('tinggal')
                        ->label('Siswa tinggal kelas / tidak lulus')
                        ->helperText('Cari berdasarkan nama atau NIS. Siswa ini tetap di tingkat yang sama.')
                        ->multiple()
                        ->searchable()
                        ->live()
                        ->columnSpanFull()
                        ->getSearchResultsUsing(fn (string $search, Get $get): array => $this->cariSiswa($search, $get('tahun_asal')))
                        ->getOptionLabelsUsing(fn (array $values): array => Siswa::whereIn('id', $values)
                            ->get()
                            ->mapWithKeys(fn (Siswa $s) => [$s->id => "{$s->nama} ({$s->nis})"])
                            ->all()),
                ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedSchema::make('form'),
            Html::make(fn () => view('filament.pages.kenaikan-kelas-pratinjau', [
                'rencana' => $this->rencana(),
            ])),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('jalankan')
                ->label('Jalankan kenaikan kelas')
                ->icon(Heroicon::OutlinedArrowTrendingUp)
                ->color('danger')
                ->disabled(fn (): bool => ! ($this->rencana()['valid'] ?? false))
                ->requiresConfirmation()
                ->modalHeading('Jalankan kenaikan kelas?')
                ->modalDescription(function (): string {
                    $t = $this->rencana()['total'] ?? [];

                    return sprintf(
                        '%d siswa naik kelas, %d tinggal kelas, dan %d siswa kelas XII dinyatakan lulus (menjadi tidak aktif). Pastikan pratinjau sudah benar.',
                        $t['naik'] ?? 0,
                        $t['tinggal'] ?? 0,
                        $t['lulus'] ?? 0,
                    );
                })
                ->modalSubmitActionLabel('Ya, jalankan')
                ->action(function () {
                    [$asal, $tujuan] = $this->tahunDipilih();

                    if (! $asal || ! $tujuan) {
                        return;
                    }

                    try {
                        $hasil = app(KenaikanKelasService::class)->jalankan($asal, $tujuan, $this->opsi());
                    } catch (Throwable $e) {
                        Notification::make()->title('Kenaikan kelas gagal')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    $this->cacheRencana = null;
                    $this->data['tinggal'] = [];

                    Notification::make()
                        ->title('Kenaikan kelas selesai')
                        ->body("{$hasil['naik']} naik, {$hasil['tinggal']} tinggal kelas, {$hasil['lulus']} lulus, {$hasil['kelas_dibuat']} kelas baru dibuat.")
                        ->success()
                        ->send();
                }),
        ];
    }

    /** @return array{0: ?TahunAjaran, 1: ?TahunAjaran} */
    private function tahunDipilih(): array
    {
        $asal = ($this->data['tahun_asal'] ?? null) ? TahunAjaran::find($this->data['tahun_asal']) : null;
        $tujuan = ($this->data['tahun_tujuan'] ?? null) ? TahunAjaran::find($this->data['tahun_tujuan']) : null;

        return [$asal, $tujuan];
    }

    private function opsi(): array
    {
        return [
            'tinggal' => $this->data['tinggal'] ?? [],
            'buat_kelas_x' => (bool) ($this->data['buat_kelas_x'] ?? false),
            'bawa_wali' => (bool) ($this->data['bawa_wali'] ?? false),
            'tanggal_lulus' => $this->data['tanggal_lulus'] ?? null,
            'aktifkan' => (bool) ($this->data['aktifkan'] ?? false),
        ];
    }

    private function rencana(): array
    {
        $kunci = md5(json_encode($this->data));

        if ($this->cacheRencana !== null && $this->kunciCache === $kunci) {
            return $this->cacheRencana;
        }

        [$asal, $tujuan] = $this->tahunDipilih();

        $rencana = ($asal && $tujuan)
            ? app(KenaikanKelasService::class)->rencana($asal, $tujuan, $this->opsi())
            : null;

        $this->kunciCache = $kunci;

        return $this->cacheRencana = $rencana ?? [
            'valid' => false,
            'masalah' => ['Pilih tahun ajaran asal dan tujuan.'],
            'kelas' => [],
            'kelas_baru' => [],
            'total' => [],
        ];
    }

    private function tujuanDefault(TahunAjaran $asal): ?TahunAjaran
    {
        return TahunAjaran::where('tanggal_mulai', '>', $asal->tanggal_mulai)->orderBy('tanggal_mulai')->first();
    }

    /** @return array<int, string> */
    private function cariSiswa(string $cari, mixed $tahunAsalId): array
    {
        if (! $tahunAsalId) {
            return [];
        }

        return DB::table('kelas_siswa as ks')
            ->join('siswa as s', 's.id', '=', 'ks.siswa_id')
            ->join('kelas as k', 'k.id', '=', 'ks.kelas_id')
            ->where('ks.tahun_ajaran_id', $tahunAsalId)
            ->where('s.is_active', true)
            ->where(fn ($q) => $q->where('s.nama', 'like', "%{$cari}%")->orWhere('s.nis', 'like', "%{$cari}%"))
            ->orderBy('s.nama')
            ->limit(50)
            ->get(['s.id', 's.nama', 's.nis', 'k.nama as kelas'])
            ->mapWithKeys(fn ($r) => [$r->id => "{$r->nama} ({$r->nis}) - {$r->kelas}"])
            ->all();
    }
}