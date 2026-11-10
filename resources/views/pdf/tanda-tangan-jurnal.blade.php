<table style="width: 100%; margin-top: 22px; page-break-inside: avoid;">
    <tr>
        <td style="width: 50%; text-align: center;">Mengetahui,<br>Kepala Sekolah</td>
        <td style="width: 50%; text-align: center;">
            {{ $sekolah->kota }}, {{ $tanggalCetak }}<br>Guru Mata Pelajaran
        </td>
    </tr>
    <tr>
        <td style="height: 62px;"></td>
        <td></td>
    </tr>
    <tr>
        <td style="text-align: center;">
            <b><u>{{ $kepsek?->name ?: '................................' }}</u></b><br>
            NIP. {{ $kepsek?->nip ?: '-' }}
        </td>
        <td style="text-align: center;">
            <b><u>{{ $rekap['guru']['nama'] }}</u></b><br>
            NIP. {{ $rekap['guru']['nip'] ?: '-' }}
        </td>
    </tr>
</table>