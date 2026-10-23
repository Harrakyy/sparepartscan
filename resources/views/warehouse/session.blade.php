@extends('layouts.app')

@section('title', 'Faktur '.$invoice->number)
@section('body-class', 'warehouse')

@section('content')
    <section id="session" class="session" data-invoice-id="{{ $invoice->id }}">
        <header class="session-head card">
            <div>
                <div class="muted">No. faktur</div>
                <div id="number" class="session-number mono">{{ $invoice->number }}</div>
            </div>
            <div class="session-stage">
                <div id="stage-label" class="stage-label">Memuat…</div>
                <div class="progress"><span id="progress-bar"></span></div>
                <div id="progress-text" class="muted"></div>
            </div>
            <div style="text-align:right">
                <div class="muted">Kepada</div>
                <div id="customer" class="session-customer">{{ $invoice->customer_name }}</div>
            </div>
        </header>

        <p id="toast" class="toast" role="alert" hidden></p>

        <div class="card">
            <div class="table-wrap">
                <table class="scan-table">
                    <thead>
                        <tr><th style="width:56px"></th><th>Kode part</th><th>Nama barang</th><th class="num" style="width:120px">Ter-scan</th></tr>
                    </thead>
                    <tbody id="items"></tbody>
                    <tbody id="wrong"></tbody>
                </table>
            </div>
        </div>

        <div class="session-actions">
            <form id="manual-form" class="row" autocomplete="off" style="flex:1">
                <div class="field">
                    <label for="manual-code">Ketik kode bila label tidak bisa di-scan</label>
                    <input id="manual-code" class="mono" maxlength="64">
                </div>
                <button class="btn" type="submit">Kirim</button>
            </form>
            <button id="complete" class="btn btn-ok btn-big" type="button" disabled>Selesai</button>
            <button id="start-qc" class="btn btn-primary btn-big" type="button" hidden>Mulai scan QC</button>
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
