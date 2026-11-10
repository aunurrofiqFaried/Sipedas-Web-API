<table style="width: 100%; margin-top: 26px; page-break-inside: avoid;">
    <tr>
        <td style="width: 50%; text-align: center;">&nbsp;<br>{{ $kiriJabatan }}</td>
        <td style="width: 50%; text-align: center;">
            {{ $sekolah->kota }}, {{ $tanggalCetak }}<br>Kepala Sekolah
        </td>
    </tr>
    <tr>
        <td style="height: 62px;"></td>
        <td></td>
    </tr>
    <tr>
        <td style="text-align: center;">
            <b><u>{{ $kiriNama ?: '................................' }}</u></b><br>
            NIP. {{ $kiriNip ?: '-' }}
        </td>
        <td style="text-align: center;">
            <b><u>{{ $kepsek?->name ?: '................................' }}</u></b><br>
            NIP. {{ $kepsek?->nip ?: '-' }}
        </td>
    </tr>
</table>