@if ($kopGambar)
    <div style="text-align: center;">
        <img src="{{ $kopGambar }}" style="width: {{ $lebarKop }}%;">
    </div>
@else
    <div style="text-align: center; border-bottom: 3px solid #000; padding-bottom: 6px;">
        @if ($sekolah->instansi_1)<div style="font-size: 12px; font-weight: bold;">{{ $sekolah->instansi_1 }}</div>@endif
        @if ($sekolah->instansi_2)<div style="font-size: 12px;">{{ $sekolah->instansi_2 }}</div>@endif
        <div style="font-size: 17px; font-weight: bold;">{{ $sekolah->nama_sekolah }}</div>
        @if ($sekolah->alamat)
            <div style="font-size: 9px;">Alamat : {{ $sekolah->alamat }} @if ($sekolah->telepon) Telepon {{ $sekolah->telepon }} @endif</div>
        @endif
        @if ($sekolah->website || $sekolah->email)
            <div style="font-size: 9px;">
                @if ($sekolah->website) Website : {{ $sekolah->website }} @endif
                @if ($sekolah->email) Email : {{ $sekolah->email }} @endif
            </div>
        @endif
    </div>
@endif