@extends('layouts.app')

@section('title', 'Laporan barang keluar')

@section('content')
    <h1>Laporan barang keluar</h1>

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
        <button class="btn btn-primary" type="submit">Tampilkan</button>
    </form>

    <p id="status" class="msg msg-bad" hidden></p>

    <section class="card">
        <h2>Total per kode part</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Kode part</th><th>Nama barang</th><th class="num">Jumlah</th></tr></thead>
                <tbody id="totals"></tbody>
            </table>
        </div>
        <p id="empty" class="muted" hidden>Tidak ada barang keluar pada rentang ini.</p>
    </section>

    <section class="card">
        <h2>Per faktur</h2>
        <div id="invoices" class="report-invoices"></div>
    </section>
@endsection

@push('scripts')
    <script type="module" src="{{ asset('js/pages/report.js') }}"></script>
@endpush
