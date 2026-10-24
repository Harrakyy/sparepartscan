@extends('layouts.app')

@section('title', 'Faktur '.$invoice->number)
@section('body-class', 'warehouse')

@section('content')
    <div id="error-flash" class="error-flash" aria-hidden="true"></div>
    <section id="session" class="session" data-invoice-id="{{ $invoice->id }}">
        <div class="session-toolbar">
            <a href="{{ url('/gudang') }}" class="btn"><i class="bi bi-arrow-left" aria-hidden="true"></i> Daftar sesi</a>
            <div class="session-toolbar-right">
                <button id="start-qc" class="btn btn-primary" type="button" hidden><i class="bi bi-shield-check" aria-hidden="true"></i> Mulai scan QC</button>
                <button id="toggle-history" class="btn" type="button" aria-expanded="false" aria-controls="history-panel">
                    <i class="bi bi-clock-history" aria-hidden="true"></i> Riwayat scan <span id="history-count" class="count-pill">0</span>
                </button>
                <button id="print" class="btn" type="button" title="Cetak hasil verifikasi">
                    <i class="bi bi-printer" aria-hidden="true"></i> Cetak
                </button>
            </div>
        </div>

        <section id="history-panel" class="card" hidden>
            <div class="row" style="justify-content:space-between;align-items:center">
                <h2>Riwayat scan</h2>
                <button id="close-history" class="btn btn-icon" type="button" aria-label="Tutup riwayat"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
            </div>
            <ul id="history" class="history"></ul>
        </section>

        <header class="session-head card">
            <div>
                <div class="label-xs">No. faktur</div>
                <div id="number" class="session-number mono">{{ $invoice->number }}</div>
            </div>
            <div class="session-stage">
                <div id="stage-label" class="stage-label">Memuat…</div>
                <div class="progress"><span id="progress-bar"></span></div>
                <div id="progress-text" class="muted"></div>
            </div>
            <div style="text-align:right">
                <div class="label-xs">Kepada</div>
                <div id="customer" class="session-customer">{{ $invoice->customer_name }}</div>
            </div>
        </header>

        <form id="manual-form" class="scan-bar" autocomplete="off">
            <button id="scan-ready" class="scan-ready" type="button" hidden>Klik di sini, lalu scan</button>
            <i class="bi bi-upc-scan muted" aria-hidden="true" style="font-size:20px"></i>
            <input id="manual-code" class="mono" maxlength="64" aria-label="Kode part"
                   placeholder="Langsung scan barang. Label rusak? Ketik kodenya lalu Enter.">
            <button class="btn" type="submit"><i class="bi bi-arrow-return-left" aria-hidden="true"></i> Kirim</button>
        </form>

        <p id="toast" class="toast" role="alert" hidden></p>

        <div class="card card-flush">
            <div class="table-wrap">
                <table class="scan-table grid">
                    <thead>
                        <tr><th style="width:56px"></th><th>Kode part</th><th>Nama barang</th><th class="num" style="width:120px">Ter-scan</th></tr>
                    </thead>
                    <tbody id="items"></tbody>
                    <tbody id="wrong"></tbody>
                </table>
            </div>
        </div>

        <div class="session-actions">
            <button id="complete" class="btn btn-ok btn-big" type="button" disabled><i class="bi bi-check2-circle" aria-hidden="true"></i> <span>Selesai</span></button>
        </div>
    </section>

    {{-- Struk 72 mm untuk printer thermal, diisi oleh JS saat tombol Cetak ditekan. --}}
    <style id="receipt-page"></style>
    <section id="receipt" class="receipt" aria-hidden="true">
        <header class="receipt-head">
            <div class="receipt-company">{{ config('app.company') }}</div>
            <div class="receipt-title">BUKTI CEK BARANG</div>
        </header>
        <dl id="receipt-meta" class="receipt-meta"></dl>
        <ol id="receipt-items" class="receipt-items"></ol>
        <p id="receipt-total" class="receipt-total"></p>
        <p id="receipt-status" class="receipt-status"></p>
        <p id="receipt-printed" class="receipt-foot"></p>
    </section>
@endsection

@push('scripts')
    <script type="module" src="{{ asset('js/pages/warehouse-session.js') }}"></script>
@endpush
