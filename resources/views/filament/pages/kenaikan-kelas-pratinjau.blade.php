@php
    $total = $rencana['total'] ?? [];
    $sel = 'padding:.5rem .75rem;border-bottom:1px solid rgba(128,128,128,.25);';
@endphp

<x-filament::section heading="Pratinjau" description="Belum ada data yang diubah. Data baru berubah setelah tombol Jalankan ditekan.">
    @if (! empty($rencana['masalah']))
        <x-filament::callout color="danger" icon="heroicon-o-exclamation-triangle" heading="Belum bisa dijalankan">
            <ul style="margin:0;padding-left:1.1rem;">
                @foreach ($rencana['masalah'] as $m)
                    <li>{{ $m }}</li>
                @endforeach
            </ul>
        </x-filament::callout>
    @endif

    @if (! empty($total))
        <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin:1rem 0;">
            <x-filament::badge color="gray">{{ $total['siswa_aktif'] }} siswa aktif</x-filament::badge>
            <x-filament::badge color="success">{{ $total['naik'] }} naik</x-filament::badge>
            <x-filament::badge color="warning">{{ $total['tinggal'] }} tinggal kelas</x-filament::badge>
            <x-filament::badge color="info">{{ $total['lulus'] }} lulus</x-filament::badge>
            <x-filament::badge color="gray">{{ $total['kelas_dibuat'] }} kelas baru dibuat</x-filament::badge>
            @if ($total['kelas_sudah_ada'] > 0)
                <x-filament::badge color="gray">{{ $total['kelas_sudah_ada'] }} kelas sudah ada (dipakai)</x-filament::badge>
            @endif
            @if ($total['dilewati'] > 0)
                <x-filament::badge color="gray">{{ $total['dilewati'] }} siswa nonaktif dilewati</x-filament::badge>
            @endif
        </div>
    @endif

    @if (! empty($rencana['kelas']))
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
                <thead>
                    <tr style="text-align:left;">
                        <th style="{{ $sel }}">Kelas sekarang</th>
                        <th style="{{ $sel }}">Siswa</th>
                        <th style="{{ $sel }}">Tujuan</th>
                        <th style="{{ $sel }}">Naik</th>
                        <th style="{{ $sel }}">Tinggal</th>
                        <th style="{{ $sel }}">Lulus</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rencana['kelas'] as $k)
                        <tr>
                            <td style="{{ $sel }}">{{ $k['nama'] }}</td>
                            <td style="{{ $sel }}">{{ $k['siswa'] }}</td>
                            <td style="{{ $sel }}">
                                @if ($k['aksi'] === 'lulus')
                                    <x-filament::badge color="info">Lulus</x-filament::badge>
                                @elseif ($k['aksi'] === 'tak_dikenal')
                                    <x-filament::badge color="danger">Nama tidak dikenali</x-filament::badge>
                                @else
                                    {{ $k['tujuan'] }}
                                @endif
                            </td>
                            <td style="{{ $sel }}">{{ $k['naik'] }}</td>
                            <td style="{{ $sel }}">{{ $k['tinggal'] }}</td>
                            <td style="{{ $sel }}">{{ $k['lulus'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament::section>