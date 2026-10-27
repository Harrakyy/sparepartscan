@extends('layouts.app')

@section('title', 'Retur baru')

@section('content')
    <div class="page-head">
        <div>
            <h1>Retur baru</h1>
            <p class="page-sub">Pilih faktur asal barang yang dikembalikan. Hanya faktur yang sudah selesai di-scan yang bisa diretur.</p>
        </div>
        <a href="{{ url('/retur') }}" class="btn"><i class="bi bi-arrow-left" aria-hidden="true"></i> Daftar retur</a>
    </div>

    <section class="card card-flush">
        <div class="card-head">
            <label class="search">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input id="search" type="search" maxlength="150" autocomplete="off" autofocus placeholder="Cari no. faktur atau nama penerima" aria-label="Cari no. faktur atau nama penerima">
            </label>
        </div>

        <p id="status" class="msg msg-bad" style="margin:16px" hidden></p>
        <div class="table-wrap">
            <table class="grid">
                <thead>
                    <tr>
                        <th class="rownum">#</th>
                        <th>No. faktur</th>
                        <th>Kepada</th>
                        <th>Dicek</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="list"></tbody>
            </table>
        </div>
        <p id="empty" class="muted" style="margin:0;padding:28px;text-align:center" hidden>
            <i class="bi bi-inbox" aria-hidden="true"></i> Tidak ada faktur yang cocok.
        </p>
    </section>
@endsection

@push('scripts')
    <script type="module" src="{{ asset('js/pages/return-create.js') }}"></script>
@endpush
