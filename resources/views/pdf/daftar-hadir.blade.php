@extends('pdf.layout')

@section('judul', 'Daftar Hadir')

@push('gaya')
    <style>
        .kepala td { padding: 2px 4px; font-size: 9.5px; vertical-align: top; }
        .k1 { width: 24%; color: #555; font-weight: bold; }
        .k2 { width: 28%; font-weight: bold; }
        .k3 { width: 14%; color: #555; font-weight: bold; }
        .k4 { width: 34%; font-weight: bold; }

        table.hadir { table-layout: fixed; margin-top: 8px; }
        .hadir th, .hadir td {
            border: 1px solid #6b7280; padding: 1px; text-align: center; font-size: 8px;
        }
        .hadir th { background: #ea580c; color: #fff; font-size: 7.5px; }
        .hadir th.tgl { background: #fed7aa; color: #7c2d12; font-size: 6px; font-weight: bold; }
        .hadir td.nama { text-align: left; padding: 2px 4px; }
        .hadir tbody tr:nth-child(even) td { background: #fff7ed; }
        .hadir thead { display: table-header-group; }
        .hadir tr { page-break-inside: avoid; }
        .hadir tr.jumlah td { background: #ffedd5; font-weight: bold; font-size: 7.5px; }
        .hadir tr.jumlah td.label { text-align: right; padding-right: 6px; }

        .dot { font-size: 7px; color: #111; }
        .t-s { color: #b45309; font-weight: bold; }
        .t-i { color: #0369a1; font-weight: bold; }
        .t-a { color: #dc2626; font-weight: bold; }
    </style>
@endpush

@section('isi')
    @php
        $perLembar = 25;
        $kolom = $rekap['kolom'];
        $siswa = $rekap['siswa'];
        $jumlahLembar = max(1, (int) ceil(count($kolom) / $perLembar));
        $konsentrasi = $rekap['kelas']['konsentrasi'];
    @endphp

    @for ($lembar = 0; $lembar < $jumlahLembar; $lembar++)
        @php $awal = $lembar * $perLembar; @endphp

        @if ($lembar === 0)
            <div class="judul">
                <h1>DAFTAR HADIR</h1>
            </div>

            <table class="kepala">
                <tr>
                    <td class="k1">MATA PELAJARAN</td>
                    <td class="k2">: {{ $rekap['mapel']['nama'] }}</td>
                    <td class="k3">PERIODE</td>
                    <td class="k4">: {{ $rekap['periode']['label'] }}</td>
                </tr>
                <tr>
                    <td class="k1">KELAS / KONSENTRASI KEAHLIAN</td>
                    <td class="k2">: {{ $rekap['kelas']['nama'] }}{{ $konsentrasi ? ' / ' . $konsentrasi : '' }}</td>
                    <td class="k3">SEMESTER</td>
                    <td class="k4">: {{ $rekap['semester'] }}</td>
                </tr>
                <tr>
                    <td class="k1"></td>
                    <td class="k2"></td>
                    <td class="k3">TAHUN PELAJARAN</td>
                    <td class="k4">: {{ $ta->nama }}</td>
                </tr>
            </table>
        @else
            <div style="page-break-before: always;"></div>
            <div class="judul">
                <h1>DAFTAR HADIR (lanjutan)</h1>
                <p>
                    {{ $rekap['mapel']['nama'] }} · {{ $rekap['kelas']['nama'] }} ·
                    Pertemuan {{ $awal + 1 }} - {{ min($awal + $perLembar, count($kolom)) }}
                </p>
            </div>
        @endif

        <table class="hadir">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 3.5%;">NO</th>
                    <th rowspan="2" style="width: 23%;">NAMA SISWA</th>
                    <th rowspan="2" style="width: 3.5%;">L/P</th>
                    @for ($i = 0; $i < $perLembar; $i++)
                        <th style="width: 2.8%;">{{ $awal + $i + 1 }}</th>
                    @endfor
                </tr>
                <tr>
                    @for ($i = 0; $i < $perLembar; $i++)
                        <th class="tgl">{{ $kolom[$awal + $i]['label'] ?? '' }}</th>
                    @endfor
                </tr>
            </thead>
            <tbody>
                @forelse ($siswa as $s)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td class="nama">{{ $s['nama'] }}</td>
                        <td>{{ $s['jk'] }}</td>
                        @for ($i = 0; $i < $perLembar; $i++)
                            @php $kode = $s['status'][$awal + $i] ?? ''; @endphp
                            <td class="{{ $kode === 'S' ? 't-s' : ($kode === 'I' ? 't-i' : ($kode === 'A' ? 't-a' : '')) }}">
                                @if ($kode === 'H')
                                    <span class="dot">&#9679;</span>
                                @else
                                    {{ $kode }}
                                @endif
                            </td>
                        @endfor
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 3 + $perLembar }}" style="padding: 14px; color: #888;">Kelas ini belum punya siswa aktif.</td>
                    </tr>
                @endforelse

                <tr class="jumlah">
                    <td colspan="3" class="label">Jumlah hadir</td>
                    @for ($i = 0; $i < $perLembar; $i++)
                        <td>{{ isset($kolom[$awal + $i]) ? $kolom[$awal + $i]['hadir'] : '' }}</td>
                    @endfor
                </tr>
                <tr class="jumlah">
                    <td colspan="3" class="label">Jumlah tidak hadir</td>
                    @for ($i = 0; $i < $perLembar; $i++)
                        <td>{{ isset($kolom[$awal + $i]) ? $kolom[$awal + $i]['tidak_hadir'] : '' }}</td>
                    @endfor
                </tr>
            </tbody>
        </table>
    @endfor

    <p style="margin-top: 6px; font-size: 8px; color: #777;">
        <span class="dot">&#9679;</span> hadir · S sakit · I izin · A alpa (tanpa keterangan) · kosong = absensi harian belum diisi.
        Tanggal kolom mengikuti jurnal mengajar; kehadiran diambil dari absensi harian kelas.
    </p>

    @include('pdf.tanda-tangan-jurnal')
@endsection