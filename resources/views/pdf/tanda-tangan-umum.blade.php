<table style="width: 100%; margin-top: 24px; page-break-inside: avoid;">
    <tr>
        <td style="width: 50%; text-align: center;">Mengetahui,<br>Kepala Sekolah</td>
        <td style="width: 50%; text-align: center;">
            {{ $sekolah->kota }}, {{ $tanggalCetak }}<br>{{ $kananJabatan }}
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
            <b><u>{{ $kananNama ?: '................................' }}</u></b><br>
            NIP. {{ $kananNip ?: '-' }}
        </td>
    </tr>
</table>