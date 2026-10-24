@extends('layouts.app')

@section('title', 'Laporan barang keluar')

@section('content')
    <div class="page-head">
        <div>
            <h1>Laporan barang keluar</h1>
            <p class="page-sub">Faktur yang scan pertamanya sudah selesai, per rentang tanggal (WIB).</p>
        </div>
        <div class="stats">
            <span class="stat"><i class="bi bi-receipt" aria-hidden="true"></i> Faktur <strong id="stat-invoices">0</strong></span>
            <span class="stat"><i class="bi bi-box-seam" aria-hidden="true"></i> Barang <strong id="stat-qty">0</strong></span>
        </div>
    </div>

    <form id="filter" class="card row" autocomplete="off">
        <div class="field">
            <label for="from">Dari tanggal</label>
            <input id="from" type="date" required>
        </div>
        <div class="field">
            <label for="to">Sampai tanggal</label>
            <input id="to" type="date" required>
        </div>
        <div class="field" style="flex:2">
            <label for="q">Nama penerima (opsional)</label>
            <input id="q" maxlength="150">
        </div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-funnel" aria-hidden="true"></i> Tampilkan</button>
    </form>

    <p id="status" class="msg msg-bad" hidden></p>

    <section class="card card-flush">
        <div class="card-head"><h2>Total per kode part</h2></div>
        <div class="table-wrap">
            <table class="grid">
                <thead><tr><th class="rownum">#</th><th>Kode part</th><th>Nama barang</th><th class="num">Jumlah</th></tr></thead>
                <tbody id="totals"></tbody>
            </table>
        </div>
        <p id="empty" class="muted" style="margin:0;padding:24px;text-align:center" hidden>Tidak ada barang keluar pada rentang ini.</p>
    </section>

    <section class="card card-flush">
        <div class="card-head"><h2>Per faktur</h2></div>
        <div id="invoices" class="report-invoices"></div>
    </section>
@endsection

@push('scripts')
    <script type="module" src="{{ asset('js/pages/report.js') }}"></script>
@endpush
