@extends('layouts.app')

@section('title', 'Faktur '.$invoice->number)
@section('body-class', 'warehouse')

@section('content')
    <section id="session" class="session" data-invoice-id="{{ $invoice->id }}">
        <a href="{{ url('/gudang') }}" class="btn" style="align-self:flex-start"><i class="bi bi-arrow-left" aria-hidden="true"></i> Daftar sesi</a>

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
            <button id="start-qc" class="btn btn-primary btn-big" type="button" hidden><i class="bi bi-shield-check" aria-hidden="true"></i> Mulai scan QC</button>
        </div>

        <details class="card">
            <summary><strong>Riwayat scan</strong></summary>
            <ul id="history" class="history"></ul>
        </details>
    </section>
@endsection

@push('scripts')
    <script type="module" src="{{ asset('js/pages/warehouse-session.js') }}"></script>
@endpush
