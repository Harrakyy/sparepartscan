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
                <button id="toggle-prints" class="btn" type="button" aria-expanded="false" aria-controls="prints-panel">
                    <i class="bi bi-receipt" aria-hidden="true"></i> Riwayat cetak <span id="prints-count" class="count-pill">0</span>
                </button>
                <button id="print" class="btn" type="button" title="Cetak hasil verifikasi" disabled>
                    <i class="bi bi-printer" aria-hidden="true"></i> Cetak
                </button>
                @can('superadmin')
                    <button id="adjust" class="btn" type="button" title="Kurangi jumlah keluar setelah retur (superadmin)" hidden>
                        <i class="bi bi-pencil-square" aria-hidden="true"></i> Edit jumlah
                    </button>
                    <button id="delete" class="btn btn-danger" type="button" title="Hapus faktur (superadmin)">
                        <i class="bi bi-trash" aria-hidden="true"></i> Hapus faktur
                    </button>
                @endcan
            </div>
        </div>

        @can('superadmin')
            <dialog id="delete-dialog" class="dialog" aria-labelledby="delete-title">
                <h2 id="delete-title">Hapus faktur {{ $invoice->number }}?</h2>
                <p id="delete-summary" class="msg msg-warn"></p>
                <p>Faktur, barang, riwayat scan, riwayat cetak, dan retur akan <strong>hilang total</strong>.
                    Setelah itu nomor ini bisa diupload lagi.</p>
                <div class="field">
                    <label for="delete-confirm">Ketik nomor faktur untuk konfirmasi</label>
                    <input id="delete-confirm" class="mono" inputmode="numeric" autocomplete="off" placeholder="{{ $invoice->number }}">
                </div>
                <p id="delete-error" class="msg msg-bad" hidden></p>
                <div class="row" style="justify-content:flex-end">
                    <button id="delete-cancel" class="btn" type="button">Batal</button>
                    <button id="delete-submit" class="btn btn-danger-solid" type="button" disabled><i class="bi bi-trash" aria-hidden="true"></i> Hapus permanen</button>
                </div>
            </dialog>

            {{-- Edit jumlah keluar: hanya mengurangi (retur), supaya sama dengan Zahir. --}}
            <section id="adjust-panel" class="card" hidden>
                <div class="row" style="justify-content:space-between;align-items:center">
                    <h2>Edit jumlah keluar</h2>
                    <span class="muted">Hanya bisa dikurangi (0 sampai jumlah faktur). Laporan barang keluar memakai angka ini.</span>
                </div>
                <div class="table-wrap">
                    <table class="grid">
                        <thead>
                            <tr><th>Kode part</th><th>Nama barang</th><th class="num" style="width:110px">Faktur</th><th class="num" style="width:140px">Keluar</th></tr>
                        </thead>
                        <tbody id="adjust-items"></tbody>
                    </table>
                </div>
                <div class="field">
                    <label for="adjust-note">Alasan (wajib)</label>
                    <input id="adjust-note" maxlength="255" placeholder="Contoh: retur 1 pcs, barang rusak">
                </div>
                <p id="adjust-error" class="msg msg-bad" hidden></p>
                <div class="row" style="justify-content:flex-end">
                    <button id="adjust-cancel" class="btn" type="button">Batal</button>
                    <button id="adjust-save" class="btn btn-primary" type="button"><i class="bi bi-check2" aria-hidden="true"></i> Simpan perubahan</button>
                </div>
            </section>
        @endcan

        <section id="history-panel" class="card" hidden>
            <div class="row" style="justify-content:space-between;align-items:center">
                <h2>Riwayat scan</h2>
                <button id="close-history" class="btn btn-icon" type="button" aria-label="Tutup riwayat"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
            </div>
            <ul id="history" class="history"></ul>
        </section>

        <section id="prints-panel" class="card" hidden>
            <div class="row" style="justify-content:space-between;align-items:center">
                <h2>Riwayat cetak</h2>
                <button id="close-prints" class="btn btn-icon" type="button" aria-label="Tutup riwayat cetak"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
            </div>
            <ul id="prints" class="history"></ul>
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

        <section id="adjustments" class="card" hidden>
            <h2>Riwayat perubahan jumlah keluar</h2>
            <ul id="adjustment-list" class="history"></ul>
        </section>

        <div class="session-actions">
            <button id="complete" class="btn btn-ok btn-big" type="button" disabled><i class="bi bi-check2-circle" aria-hidden="true"></i> <span>Selesai</span></button>
        </div>
    </section>

    {{-- Struk 72 mm untuk printer thermal, diisi oleh JS saat tombol Cetak ditekan. --}}
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
