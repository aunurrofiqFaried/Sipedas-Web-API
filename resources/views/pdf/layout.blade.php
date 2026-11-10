<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>@yield('judul')</title>
    <style>
        @page { margin: 24px 30px 52px 30px; }

        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1f2937; }

        table { border-collapse: collapse; width: 100%; }

        .judul { text-align: center; margin: 12px 0 10px; }
        .judul h1 { font-size: 14px; margin: 0; letter-spacing: 0.6px; }
        .judul p { margin: 3px 0 0; font-size: 10px; color: #555; }

        .info td { padding: 2px 4px; font-size: 10px; }
        .info td:nth-child(odd) { width: 16%; color: #555; }
        .info td:nth-child(even) { width: 34%; font-weight: bold; }

        h2.bagian {
            font-size: 11px; margin: 14px 0 6px; padding-left: 6px;
            border-left: 3px solid #ea580c; color: #7c2d12;
        }

        .data th {
            background: #ea580c; color: #fff; font-size: 9px;
            padding: 6px 5px; border: 1px solid #c2410c; text-align: center;
        }
        .data td { padding: 4px 5px; border: 1px solid #d1d5db; font-size: 9.5px; }
        .data thead { display: table-header-group; }
        .data tr { page-break-inside: avoid; }
        .data tbody tr:nth-child(even) td { background: #fff7ed; }

        .tengah { text-align: center; }
        .redup { color: #9ca3af; }

        .trek { background: #e5e7eb; height: 8px; }
        .isi { height: 8px; }

        .footer {
            position: fixed; bottom: -36px; left: 0; right: 0;
            font-size: 8px; color: #888; border-top: 1px solid #ddd; padding-top: 4px;
        }
        .pagenum:before { content: counter(page); }
    </style>
    @stack('gaya')
</head>
<body>
    <div class="footer">
        Dicetak dari Sipedas · {{ $sekolah->nama_sekolah }} · {{ $tanggalCetak }}
        <span style="float: right;">Halaman <span class="pagenum"></span></span>
    </div>

    @include('pdf.kop')

    @yield('isi')
</body>
</html>