@extends('pdf.layout')

@section('judul', 'Rekap Poin ' . $rekap['kelas']['nama'])

@section('isi')
    @php
        $r = $rekap['ringkasan'];
        $warnaKategori = ['ringan' => '#16a34a', 'sedang' => '#d97706', 'berat' => '#dc2626', 'lainnya' => '#6b7280'];
        $warnaLevel = [0 => '#16a34a', 1 => '#d97706', 2 => '#ea580c', 3 => '#dc2626'];
        $warnaDari = fn (int $level) => $warnaLevel[min($level, 3)];
        $totalKejadian = max(1, $r['kejadian']);
        $batasMaks = max(1, (int) collect($rekap['aturan'])->max('batas_poin'), (int) collect($rekap['siswa'])->max('poin_aktif'));
        $maksJenis = max(1, (int) collect($rekap['jenis_terbanyak'])->max('jumlah'));
        $maksBulan = max(1, (int) collect($rekap['per_bulan'])->max('jumlah'));
        $wali = $rekap['wali'];
    @endphp

    <div class="judul">
        <h1>REKAP POIN PELANGGARAN SISWA</h1>
        <p>{{ $rekap['periode']['label'] }}</p>
    </div>

    <table class="info">
        <tr>
            <td>Kelas</td><td>: {{ $rekap['kelas']['nama'] }}</td>
            <td>Tahun ajaran</td><td>: {{ $rekap['tahun_ajaran'] }}</td>
        </tr>
        <tr>
            <td>Wali kelas</td><td>: {{ $wali['nama'] ?? '-' }}</td>
            <td>Jumlah siswa</td><td>: {{ $r['jumlah_siswa'] }} siswa</td>
        </tr>
    </table>

    {{-- Ringkasan --}}
    <h2 class="bagian">Ringkasan</h2>

    <table style="table-layout: fixed;">
        <tr>
            <td style="text-align: center; padding: 6px 2px; border: 1px solid #ea580c; background: #fff7ed;">
                <div style="font-size: 15px; font-weight: bold; color: #c2410c;">{{ $r['kejadian'] }}</div>
                <div style="font-size: 8px; color: #555;">Pelanggaran dicatat</div>
            </td>
            <td style="text-align: center; padding: 6px 2px; border: 1px solid #dc2626; background: #fef2f2;">
                <div style="font-size: 15px; font-weight: bold; color: #b91c1c;">{{ $r['poin_pelanggaran'] }}</div>
                <div style="font-size: 8px; color: #555;">Total poin pelanggaran</div>
            </td>
            <td style="text-align: center; padding: 6px 2px; border: 1px solid #16a34a; background: #f0fdf4;">
                <div style="font-size: 15px; font-weight: bold; color: #15803d;">{{ $r['remisi'] }}</div>
                <div style="font-size: 8px; color: #555;">Total remisi</div>
            </td>
            <td style="text-align: center; padding: 6px 2px; border: 1px solid #d97706; background: #fffbeb;">
                <div style="font-size: 15px; font-weight: bold; color: #b45309;">{{ $r['siswa_bermasalah'] }}<span style="font-size: 9px; font-weight: normal;"> / {{ $r['jumlah_siswa'] }}</span></div>
                <div style="font-size: 8px; color: #555;">Siswa melanggar</div>
            </td>
            <td style="text-align: center; padding: 6px 2px; border: 1px solid #dc2626; background: #fef2f2;">
                <div style="font-size: 15px; font-weight: bold; color: #b91c1c;">{{ $r['siswa_sp'] }}</div>
                <div style="font-size: 8px; color: #555;">Siswa terkena SP</div>
            </td>
        </tr>
    </table>

    {{-- Grafik 1: kategori pelanggaran --}}
    <h2 class="bagian">Kategori pelanggaran</h2>

    @if ($r['kejadian'] > 0)
        <table style="table-layout: fixed;">
            <tr>
                @foreach ($rekap['kategori'] as $k)
                    @php $p = round($k['jumlah'] / $totalKejadian * 100, 1); @endphp
                    <td style="width: {{ $p }}%; background: {{ $warnaKategori[$k['kategori']] ?? '#6b7280' }}; color: #fff; text-align: center; padding: 6px 0; font-size: 8px; font-weight: bold;">
                        {{ $p >= 12 ? ucfirst($k['kategori']) . ' ' . $k['jumlah'] : '' }}
                    </td>
                @endforeach
            </tr>
        </table>
        <p style="margin: 4px 0 0; font-size: 8px; color: #666;">
            @foreach ($rekap['kategori'] as $k)
                <span style="color: {{ $warnaKategori[$k['kategori']] ?? '#6b7280' }};">&#9632;</span>
                {{ ucfirst($k['kategori']) }}: {{ $k['jumlah'] }} kejadian ({{ $k['poin'] }} poin) &nbsp;
            @endforeach
        </p>
    @else
        <p style="color: #888;">Tidak ada pelanggaran pada periode ini.</p>
    @endif

    {{-- Grafik 2 dan 3: berdampingan --}}
    @if ($r['kejadian'] > 0)
        <table style="margin-top: 4px; table-layout: fixed;">
            <tr>
                <td style="width: 52%; vertical-align: top; padding-right: 10px;">
                    <h2 class="bagian">Pelanggaran terbanyak</h2>
                    <table>
                        @foreach ($rekap['jenis_terbanyak'] as $j)
                            <tr>
                                <td style="width: 46%; padding: 2px 3px; font-size: 8px;">{{ $j['nama'] }}</td>
                                <td style="width: 38%; padding: 2px 3px;">
                                    <div class="trek"><div class="isi" style="width: {{ round($j['jumlah'] / $maksJenis * 100) }}%; background: #ea580c;"></div></div>
                                </td>
                                <td style="width: 16%; padding: 2px 3px; text-align: right; font-size: 8px; font-weight: bold;">{{ $j['jumlah'] }}x</td>
                            </tr>
                        @endforeach
                    </table>
                </td>
                <td style="width: 48%; vertical-align: top;">
                    <h2 class="bagian">Tren per bulan</h2>
                    <table>
                        @foreach ($rekap['per_bulan'] as $b)
                            <tr>
                                <td style="width: 28%; padding: 2px 3px; font-size: 8px;">{{ $b['label'] }}</td>
                                <td style="width: 52%; padding: 2px 3px;">
                                    <div class="trek"><div class="isi" style="width: {{ round($b['jumlah'] / $maksBulan * 100) }}%; background: #dc2626;"></div></div>
                                </td>
                                <td style="width: 20%; padding: 2px 3px; text-align: right; font-size: 8px; font-weight: bold;">{{ $b['jumlah'] }}</td>
                            </tr>
                        @endforeach
                    </table>
                </td>
            </tr>
        </table>
    @endif

    {{-- Tabel siswa --}}
    <h2 class="bagian">Rincian per siswa</h2>

    <table class="data">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 11%;">NIS</th>
                <th>Nama siswa</th>
                <th style="width: 8%;">Kejadian</th>
                <th style="width: 8%;">Poin</th>
                <th style="width: 8%;">Remisi</th>
                <th style="width: 22%;">Poin aktif</th>
                <th style="width: 13%;">SP</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rekap['siswa'] as $s)
                @php $warna = $warnaDari($s['level']); @endphp
                <tr>
                    <td class="tengah">{{ $loop->iteration }}</td>
                    <td class="tengah">{{ $s['nis'] }}</td>
                    <td>{{ $s['nama'] }}</td>
                    <td class="tengah {{ $s['kejadian'] === 0 ? 'redup' : '' }}">{{ $s['kejadian'] ?: '-' }}</td>
                    <td class="tengah {{ $s['poin_pelanggaran'] === 0 ? 'redup' : '' }}">{{ $s['poin_pelanggaran'] ?: '-' }}</td>
                    <td class="tengah {{ $s['remisi'] === 0 ? 'redup' : '' }}">{{ $s['remisi'] ?: '-' }}</td>
                    <td>
                        <table>
                            <tr>
                                <td style="border: 0; padding: 0; width: 68%;">
                                    <div class="trek"><div class="isi" style="width: {{ min(100, round($s['poin_aktif'] / $batasMaks * 100)) }}%; background: {{ $warna }};"></div></div>
                                </td>
                                <td style="border: 0; padding: 0 0 0 4px; text-align: right; font-weight: bold; color: {{ $warna }};">{{ $s['poin_aktif'] }}</td>
                            </tr>
                        </table>
                    </td>
                    <td class="tengah" style="font-size: 8px;">
                        @if ($s['sp'])
                            <b style="color: {{ $warnaDari($s['sp']['level']) }};">{{ $s['sp']['nama'] }}</b><br>{{ ucfirst($s['sp']['status']) }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="tengah redup" style="padding: 14px;">Tidak ada siswa yang melanggar pada periode ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p style="margin-top: 6px; font-size: 8px; color: #777;">
        Poin aktif = poin pelanggaran dikurangi remisi pada periode ini (minimal 0).
        Warna batang mengikuti ambang SP:
        @foreach ($rekap['aturan'] as $a)
            <b style="color: {{ $warnaDari($a['level']) }};">{{ $a['nama'] }} &ge; {{ $a['batas_poin'] }}</b>{{ ! $loop->last ? ' · ' : '' }}
        @endforeach
        · hijau di bawah ambang pertama. Status SP berasal dari usulan SP tahun ajaran berjalan.
    </p>

    @include('pdf.tanda-tangan-umum', [
        'kananJabatan' => $wali ? 'Wali Kelas' : 'Guru',
        'kananNama' => $wali['nama'] ?? $pencetak->name,
        'kananNip' => $wali ? $wali['nip'] : $pencetak->nip,
    ])
@endsection