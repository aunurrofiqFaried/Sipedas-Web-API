@extends('pdf.layout')

@section('judul', 'Jurnal Kegiatan Guru')

@section('isi')
    @php
        $r = $rekap['ringkasan'];
        $baris = $rekap['baris'];
    @endphp

    <div class="judul">
        <h1>JURNAL KEGIATAN GURU</h1>
    </div>

    <table class="info">
        <tr>
            <td>Nama guru</td><td>: {{ $rekap['guru']['nama'] }}</td>
            <td>Mata pelajaran</td><td>: {{ $rekap['mapel']['nama'] }}</td>
        </tr>
        <tr>
            <td>NIP</td><td>: {{ $rekap['guru']['nip'] ?: '-' }}</td>
            <td>Kelas</td><td>: {{ $rekap['kelas']['nama'] ?? 'Semua kelas yang diampu' }}</td>
        </tr>
        <tr>
            <td>Tahun ajaran</td><td>: {{ $ta->nama }}</td>
            <td>Periode</td><td>: {{ $rekap['periode']['label'] }}</td>
        </tr>
    </table>

    <table class="data" style="margin-top: 10px;">
        <thead>
            <tr>
                <th rowspan="2" style="width: 4%;">No</th>
                <th rowspan="2" style="width: 11%;">Hari / Tanggal</th>
                <th rowspan="2" style="width: 14%;">Kelas / Konsentrasi</th>
                <th rowspan="2" style="width: 6%;">Jam ke</th>
                <th colspan="3">Presensi siswa</th>
                <th rowspan="2" style="width: 15%;">Siswa tidak hadir</th>
                <th rowspan="2" style="width: 28%;">Kegiatan / isi jurnal</th>
                <th rowspan="2" style="width: 8%;">Paraf</th>
            </tr>
            <tr>
                <th style="width: 4.5%;">Hadir</th>
                <th style="width: 4.5%;">Absen</th>
                <th style="width: 4.5%;">Jml</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($baris as $b)
                <tr>
                    <td class="tengah">{{ $loop->iteration }}</td>
                    <td class="tengah">{{ $b['hari'] }}<br>{{ $b['tanggal_label'] }}</td>
                    <td>
                        <b>{{ $b['kelas'] ?? '-' }}</b>
                        @if ($b['konsentrasi'])
                            <br><span style="font-size: 8px; color: #555;">{{ $b['konsentrasi'] }}</span>
                        @endif
                    </td>
                    <td class="tengah">
                        @if ($b['jam_ke_mulai'] !== null)
                            {{ $b['jam_ke_mulai'] }}{{ $b['jam_ke_selesai'] !== $b['jam_ke_mulai'] ? '-' . $b['jam_ke_selesai'] : '' }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="tengah">{{ $b['hadir'] ?? '-' }}</td>
                    <td class="tengah">{{ $b['tidak_hadir'] ?? '-' }}</td>
                    <td class="tengah">{{ $b['jumlah_siswa'] ?: '-' }}</td>
                    <td>{{ count($b['nama_tidak_hadir']) ? implode(', ', $b['nama_tidak_hadir']) : '-' }}</td>
                    <td>
                        {!! nl2br(e($b['materi'])) !!}
                        @if ($b['catatan'])
                            <br><span style="font-size: 8px; color: #666;"><i>Catatan: {{ $b['catatan'] }}</i></span>
                        @endif
                    </td>
                    <td style="height: 32px;"></td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="tengah redup" style="padding: 16px;">
                        Tidak ada jurnal pada periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table style="margin-top: 10px; table-layout: fixed;">
        <tr>
            <td style="text-align: center; padding: 6px; border: 1px solid #ea580c; background: #fff7ed;">
                <div style="font-size: 15px; font-weight: bold; color: #c2410c;">{{ $r['pertemuan'] }}</div>
                <div style="font-size: 8px; color: #555;">Jumlah pertemuan</div>
            </td>
            <td style="text-align: center; padding: 6px; border: 1px solid #ea580c; background: #fff7ed;">
                <div style="font-size: 15px; font-weight: bold; color: #c2410c;">{{ $r['jam_pelajaran'] }}</div>
                <div style="font-size: 8px; color: #555;">Total jam pelajaran</div>
            </td>
            <td style="text-align: center; padding: 6px; border: 1px solid #16a34a; background: #f0fdf4;">
                <div style="font-size: 15px; font-weight: bold; color: #15803d;">{{ $r['rata_kehadiran'] !== null ? $r['rata_kehadiran'] . '%' : '-' }}</div>
                <div style="font-size: 8px; color: #555;">Rata-rata kehadiran siswa</div>
            </td>
        </tr>
    </table>

    <p style="margin-top: 6px; font-size: 8px; color: #777;">
        Presensi diambil dari absensi harian kelas pada tanggal jurnal. Absen = sakit, izin, dan alpa.
    </p>

    @include('pdf.tanda-tangan-jurnal')
@endsection