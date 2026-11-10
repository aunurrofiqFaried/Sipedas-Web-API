@extends('pdf.layout')

@section('judul', 'Rekap Sholat ' . $rekap['kelas']['nama'])

@section('isi')
    @php
        $r = $rekap['ringkasan'];
        $wali = $rekap['wali'];
        $warnaPersen = fn (?int $p) => $p === null ? '#9ca3af' : ($p >= 90 ? '#16a34a' : ($p >= 75 ? '#d97706' : '#dc2626'));
        $teks = fn (?int $p) => $p === null ? '-' : $p . '%';
        $maksTidak = max(1, (int) collect($rekap['terbanyak_tidak_hadir'])->max('jumlah'));
        $adaData = ($rekap['waktu']['dhuhur']['hadir'] + $rekap['waktu']['dhuhur']['tidak_hadir'] + $rekap['waktu']['dhuhur']['berhalangan']
            + $rekap['waktu']['ashar']['hadir'] + $rekap['waktu']['ashar']['tidak_hadir'] + $rekap['waktu']['ashar']['berhalangan']) > 0;
    @endphp

    <div class="judul">
        <h1>REKAP KEHADIRAN SHOLAT</h1>
        <p>{{ $rekap['periode']['label'] }}</p>
    </div>

    <table class="info">
        <tr>
            <td>Kelas</td><td>: {{ $rekap['kelas']['nama'] }}</td>
            <td>Tahun ajaran</td><td>: {{ $rekap['tahun_ajaran'] }}</td>
        </tr>
        <tr>
            <td>Wali kelas</td><td>: {{ $wali['nama'] ?? '-' }}</td>
            <td>Peserta</td><td>: {{ $r['peserta'] }} siswa muslim</td>
        </tr>
    </table>

    {{-- Ringkasan --}}
    <h2 class="bagian">Ringkasan</h2>

    <table style="table-layout: fixed;">
        <tr>
            <td style="text-align: center; padding: 6px 2px; border: 1px solid #ea580c; background: #fff7ed;">
                <div style="font-size: 15px; font-weight: bold; color: #c2410c;">{{ $r['peserta'] }}</div>
                <div style="font-size: 8px; color: #555;">Peserta</div>
            </td>
            <td style="text-align: center; padding: 6px 2px; border: 1px solid #ea580c; background: #fff7ed;">
                <div style="font-size: 15px; font-weight: bold; color: #c2410c;">{{ $r['hari_tercatat'] }}</div>
                <div style="font-size: 8px; color: #555;">Hari tercatat</div>
            </td>
            <td style="text-align: center; padding: 6px 2px; border: 1px solid #16a34a; background: #f0fdf4;">
                <div style="font-size: 15px; font-weight: bold; color: {{ $warnaPersen($r['persen_dhuhur']) }};">{{ $teks($r['persen_dhuhur']) }}</div>
                <div style="font-size: 8px; color: #555;">Hadir Dhuhur</div>
            </td>
            <td style="text-align: center; padding: 6px 2px; border: 1px solid #16a34a; background: #f0fdf4;">
                <div style="font-size: 15px; font-weight: bold; color: {{ $warnaPersen($r['persen_ashar']) }};">{{ $teks($r['persen_ashar']) }}</div>
                <div style="font-size: 8px; color: #555;">Hadir Ashar</div>
            </td>
            <td style="text-align: center; padding: 6px 2px; border: 1px solid #16a34a; background: #f0fdf4;">
                <div style="font-size: 15px; font-weight: bold; color: {{ $warnaPersen($r['persen']) }};">{{ $teks($r['persen']) }}</div>
                <div style="font-size: 8px; color: #555;">Rata-rata hadir</div>
            </td>
        </tr>
    </table>

    {{-- Grafik 1: per waktu sholat --}}
    <h2 class="bagian">Kehadiran per waktu sholat</h2>

    @if ($adaData)
        <table>
            @foreach (['dhuhur' => 'Dhuhur', 'ashar' => 'Ashar'] as $kunci => $nama)
                @php
                    $w = $rekap['waktu'][$kunci];
                    $tot = max(1, $w['hadir'] + $w['tidak_hadir'] + $w['berhalangan']);
                @endphp
                <tr>
                    <td style="width: 11%; padding: 3px 0; font-weight: bold; font-size: 9px;">{{ $nama }}</td>
                    <td style="width: 89%; padding: 3px 0;">
                        <table style="table-layout: fixed;">
                            <tr>
                                @foreach ([['hadir', '#16a34a'], ['tidak_hadir', '#dc2626'], ['berhalangan', '#d97706']] as [$k, $warna])
                                    @if ($w[$k] > 0)
                                        <td style="width: {{ round($w[$k] / $tot * 100, 1) }}%; background: {{ $warna }}; color: #fff; text-align: center; padding: 5px 0; font-size: 8px; font-weight: bold;">
                                            {{ round($w[$k] / $tot * 100) >= 8 ? $w[$k] : '' }}
                                        </td>
                                    @endif
                                @endforeach
                            </tr>
                        </table>
                    </td>
                </tr>
            @endforeach
        </table>
        <p style="margin: 3px 0 0; font-size: 8px; color: #666;">
            <span style="color: #16a34a;">&#9632;</span> Hadir &nbsp;
            <span style="color: #dc2626;">&#9632;</span> Tidak hadir &nbsp;
            <span style="color: #d97706;">&#9632;</span> Berhalangan
        </p>
    @else
        <p style="color: #888;">Belum ada absensi sholat pada periode ini.</p>
    @endif

    {{-- Grafik 2 dan 3 --}}
    @if ($adaData)
        <table style="margin-top: 4px; table-layout: fixed;">
            <tr>
                <td style="width: 52%; vertical-align: top; padding-right: 10px;">
                    <h2 class="bagian">Tren kehadiran per bulan</h2>
                    <table>
                        @foreach ($rekap['per_bulan'] as $b)
                            <tr>
                                <td style="width: 26%; padding: 2px 3px; font-size: 8px;">{{ $b['label'] }}</td>
                                <td style="width: 54%; padding: 2px 3px;">
                                    <div class="trek"><div class="isi" style="width: {{ $b['persen'] ?? 0 }}%; background: {{ $warnaPersen($b['persen']) }};"></div></div>
                                </td>
                                <td style="width: 20%; padding: 2px 3px; text-align: right; font-size: 8px; font-weight: bold;">{{ $teks($b['persen']) }}</td>
                            </tr>
                        @endforeach
                    </table>
                </td>
                <td style="width: 48%; vertical-align: top;">
                    <h2 class="bagian">Paling sering tidak hadir</h2>
                    @if (count($rekap['terbanyak_tidak_hadir']) > 0)
                        <table>
                            @foreach ($rekap['terbanyak_tidak_hadir'] as $s)
                                <tr>
                                    <td style="width: 44%; padding: 2px 3px; font-size: 8px;">{{ $s['nama'] }}</td>
                                    <td style="width: 40%; padding: 2px 3px;">
                                        <div class="trek"><div class="isi" style="width: {{ round($s['jumlah'] / $maksTidak * 100) }}%; background: #dc2626;"></div></div>
                                    </td>
                                    <td style="width: 16%; padding: 2px 3px; text-align: right; font-size: 8px; font-weight: bold; color: #dc2626;">{{ $s['jumlah'] }}x</td>
                                </tr>
                            @endforeach
                        </table>
                    @else
                        <p style="color: #888; font-size: 9px;">Tidak ada siswa yang tidak hadir.</p>
                    @endif
                </td>
            </tr>
        </table>
    @endif

    {{-- Tabel siswa --}}
    <h2 class="bagian">Rincian per siswa</h2>

    <table class="data">
        <thead>
            <tr>
                <th rowspan="2" style="width: 4%;">No</th>
                <th rowspan="2">Nama siswa</th>
                <th rowspan="2" style="width: 4%;">L/P</th>
                <th colspan="3">Dhuhur</th>
                <th colspan="3">Ashar</th>
                <th rowspan="2" style="width: 24%;">Kehadiran</th>
            </tr>
            <tr>
                <th style="width: 5.5%;">H</th><th style="width: 5.5%;">T</th><th style="width: 5.5%;">B</th>
                <th style="width: 5.5%;">H</th><th style="width: 5.5%;">T</th><th style="width: 5.5%;">B</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rekap['siswa'] as $s)
                <tr>
                    <td class="tengah">{{ $loop->iteration }}</td>
                    <td>{{ $s['nama'] }}</td>
                    <td class="tengah">{{ $s['jk'] }}</td>
                    @foreach (['dhuhur', 'ashar'] as $w)
                        <td class="tengah {{ $s[$w]['hadir'] === 0 ? 'redup' : '' }}">{{ $s[$w]['hadir'] ?: '-' }}</td>
                        <td class="tengah {{ $s[$w]['tidak_hadir'] === 0 ? 'redup' : '' }}" @if ($s[$w]['tidak_hadir'] > 0) style="color: #dc2626; font-weight: bold;" @endif>{{ $s[$w]['tidak_hadir'] ?: '-' }}</td>
                        <td class="tengah {{ $s[$w]['berhalangan'] === 0 ? 'redup' : '' }}">{{ $s[$w]['berhalangan'] ?: '-' }}</td>
                    @endforeach
                    <td>
                        <table>
                            <tr>
                                <td style="border: 0; padding: 0; width: 68%;">
                                    <div class="trek"><div class="isi" style="width: {{ $s['persen'] ?? 0 }}%; background: {{ $warnaPersen($s['persen']) }};"></div></div>
                                </td>
                                <td style="border: 0; padding: 0 0 0 4px; text-align: right; font-weight: bold;">{{ $teks($s['persen']) }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="tengah redup" style="padding: 14px;">Kelas ini belum punya siswa muslim aktif.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p style="margin-top: 6px; font-size: 8px; color: #777;">
        H hadir · T tidak hadir · B berhalangan (tidak dihitung dalam persentase).
        Persentase = hadir dibagi (hadir + tidak hadir). Hijau: 90% ke atas · Kuning: 75-89% · Merah: di bawah 75%.
        Siswa yang sakit, izin, atau alpa pada absensi pagi tidak ikut dihitung pada hari itu.
    </p>

    @include('pdf.tanda-tangan-umum', [
        'kananJabatan' => $wali ? 'Wali Kelas' : 'Guru',
        'kananNama' => $wali['nama'] ?? $pencetak->name,
        'kananNip' => $wali ? $wali['nip'] : $pencetak->nip,
    ])
@endsection