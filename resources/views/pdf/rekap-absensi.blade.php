@extends('pdf.layout')

@section('judul', 'Rekap Kehadiran ' . $kelas->nama)

@section('isi')
    @php
        $total = $rekap['total'];
        $jumlah = $total['hadir'] + $total['sakit'] + $total['izin'] + $total['alpa'];
        $persen = fn ($n) => $jumlah > 0 ? round($n / $jumlah * 100, 1) : 0;
        $warnaStatus = ['hadir' => '#16a34a', 'sakit' => '#d97706', 'izin' => '#0284c7', 'alpa' => '#dc2626'];
    @endphp

    <div class="judul">
        <h1>REKAP KEHADIRAN SISWA</h1>
        <p>{{ $rekap['periode']['label'] }}</p>
    </div>

    <table class="info">
        <tr>
            <td>Kelas</td><td>: {{ $kelas->nama }}</td>
            <td>Tahun ajaran</td><td>: {{ $ta->nama }}</td>
        </tr>
        <tr>
            <td>Wali kelas</td><td>: {{ $kelas->waliKelas?->name ?? '-' }}</td>
            <td>Jumlah siswa</td><td>: {{ count($siswa) }} siswa</td>
        </tr>
        <tr>
            <td>Periode</td><td>: {{ $rekap['periode']['label'] }}</td>
            <td>Hari tercatat</td><td>: {{ $rekap['hari_tercatat'] }} hari</td>
        </tr>
    </table>

    {{-- Grafik 1: distribusi kehadiran --}}
    <h2 class="bagian">Ringkasan kehadiran</h2>

    <table style="table-layout: fixed;">
        <tr>
            @foreach ($warnaStatus as $status => $warna)
                @if ($total[$status] > 0)
                    <td style="width: {{ $persen($total[$status]) }}%; background: {{ $warna }}; color: #fff; text-align: center; padding: 6px 0; font-size: 8px; font-weight: bold;">
                        {{ $persen($total[$status]) >= 8 ? ucfirst($status) . ' ' . $persen($total[$status]) . '%' : '' }}
                    </td>
                @endif
            @endforeach
            @if ($jumlah === 0)
                <td style="background: #e5e7eb; text-align: center; padding: 6px 0;">Belum ada data absensi pada periode ini</td>
            @endif
        </tr>
    </table>

    <table style="margin-top: 6px; table-layout: fixed;">
        <tr>
            @foreach ($warnaStatus as $status => $warna)
                <td style="text-align: center; padding: 6px 2px; border: 1px solid {{ $warna }}; background: #fff;">
                    <div style="font-size: 15px; font-weight: bold; color: {{ $warna }};">{{ $total[$status] }}</div>
                    <div style="font-size: 8px; color: #555;">{{ ucfirst($status) }} · {{ $persen($total[$status]) }}%</div>
                </td>
            @endforeach
            <td style="text-align: center; padding: 6px 2px; border: 1px solid #ea580c; background: #fff7ed;">
                <div style="font-size: 15px; font-weight: bold; color: #c2410c;">{{ $rata !== null ? $rata . '%' : '-' }}</div>
                <div style="font-size: 8px; color: #555;">Rata-rata kehadiran</div>
            </td>
        </tr>
    </table>

    {{-- Grafik 2: siswa dengan alpa terbanyak --}}
    @if ($alpaTerbanyak->isNotEmpty())
        @php $maks = max(1, $alpaTerbanyak->max('alpa')); @endphp

        <h2 class="bagian">Siswa dengan alpa terbanyak</h2>

        <table>
            @foreach ($alpaTerbanyak as $s)
                <tr>
                    <td style="width: 34%; padding: 3px 4px;">{{ $s['nama'] }}</td>
                    <td style="width: 54%; padding: 3px 4px;">
                        <div class="trek"><div class="isi" style="width: {{ round($s['alpa'] / $maks * 100) }}%; background: #dc2626;"></div></div>
                    </td>
                    <td style="width: 12%; padding: 3px 4px; text-align: right; font-weight: bold; color: #dc2626;">{{ $s['alpa'] }} hari</td>
                </tr>
            @endforeach
        </table>
    @endif

    {{-- Tabel siswa --}}
    <h2 class="bagian">Rincian per siswa</h2>

    <table class="data">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 14%;">NIS</th>
                <th>Nama siswa</th>
                <th style="width: 6%;">H</th>
                <th style="width: 6%;">S</th>
                <th style="width: 6%;">I</th>
                <th style="width: 6%;">A</th>
                <th style="width: 22%;">Kehadiran</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($siswa as $s)
                @php
                    $p = $s['persen'];
                    $warnaBatang = $p === null ? '#9ca3af' : ($p >= 90 ? '#16a34a' : ($p >= 75 ? '#d97706' : '#dc2626'));
                @endphp
                <tr>
                    <td class="tengah">{{ $loop->iteration }}</td>
                    <td class="tengah">{{ $s['nis'] }}</td>
                    <td>{{ $s['nama'] }}</td>
                    <td class="tengah {{ $s['hadir'] === 0 ? 'redup' : '' }}">{{ $s['hadir'] ?: '-' }}</td>
                    <td class="tengah {{ $s['sakit'] === 0 ? 'redup' : '' }}">{{ $s['sakit'] ?: '-' }}</td>
                    <td class="tengah {{ $s['izin'] === 0 ? 'redup' : '' }}">{{ $s['izin'] ?: '-' }}</td>
                    <td class="tengah {{ $s['alpa'] === 0 ? 'redup' : '' }}" @if ($s['alpa'] > 0) style="color: #dc2626; font-weight: bold;" @endif>{{ $s['alpa'] ?: '-' }}</td>
                    <td>
                        <table>
                            <tr>
                                <td style="border: 0; padding: 0; width: 70%;">
                                    <div class="trek"><div class="isi" style="width: {{ $p ?? 0 }}%; background: {{ $warnaBatang }};"></div></div>
                                </td>
                                <td style="border: 0; padding: 0 0 0 4px; text-align: right; font-weight: bold;">{{ $p !== null ? $p . '%' : '-' }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            @endforeach

            @if (count($siswa) === 0)
                <tr><td colspan="8" class="tengah redup" style="padding: 14px;">Kelas ini belum punya siswa aktif.</td></tr>
            @endif
        </tbody>
    </table>

    <p style="margin-top: 6px; font-size: 8px; color: #777;">
        H hadir · S sakit · I izin · A alpa. Persentase kehadiran = hadir dibagi hari yang tercatat.
        Hijau: 90% ke atas · Kuning: 75-89% · Merah: di bawah 75%.
    </p>

    @include('pdf.tanda-tangan', [
        'kiriJabatan' => $kelas->waliKelas ? 'Wali Kelas' : 'Guru',
        'kiriNama' => $kelas->waliKelas?->name ?? $pencetak->name,
        'kiriNip' => $kelas->waliKelas?->nip ?? ($kelas->waliKelas ? null : $pencetak->nip),
    ])
@endsection