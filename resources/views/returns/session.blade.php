@extends('layouts.app')

@section('title', 'Retur '.$goodsReturn->invoice->number)

@section('content')
    <div id="error-flash" class="error-flash" aria-hidden="true"></div>
    <section id="return" class="session" data-return-id="{{ $goodsReturn->id }}">
        <div class="session-toolbar">
            <a href="{{ url('/retur') }}" class="btn"><i class="bi bi-arrow-left" aria-hidden="true"></i> Daftar retur</a>
            <div class="session-toolbar-right">
                <button id="cancel" class="btn" type="button" hidden><i class="bi bi-trash" aria-hidden="true"></i> Batalkan retur</button>
            </div>
        </div>

        <header class="session-head card">
            <div>
                <div class="label-xs">No. faktur</div>
                <div class="session-number mono">{{ $goodsReturn->invoice->number }}</div>
            </div>
            <div class="session-stage">
                <div id="stage-label" class="stage-label">Memuat…</div>
                <div id="totals" class="muted"></div>
            </div>
            <div style="text-align:right">
                <div class="label-xs">Kepada</div>
                <div class="session-customer">{{ $goodsReturn->invoice->customer_name }}</div>
            </div>
        </header>

        <label class="card return-note">
            <span class="label-xs">Catatan alasan retur</span>
            <textarea id="note" maxlength="500" rows="2" placeholder="Contoh: salah kirim tipe, rusak di jalan (opsional)"></textarea>
        </label>

        <div id="modes" class="return-modes" role="group" aria-label="Kondisi barang yang di-scan">
            <button id="mode-good" class="return-mode return-mode-good" type="button" aria-pressed="true">
                <i class="bi bi-check-circle-fill" aria-hidden="true"></i> BAGUS <small>masuk stok lagi</small>
            </button>
            <button id="mode-bad" class="return-mode return-mode-bad" type="button" aria-pressed="false">
                <i class="bi bi-x-octagon-fill" aria-hidden="true"></i> JELEK <small>tidak masuk stok</small>
            </button>
        </div>

        <form id="manual-form" class="scan-bar" autocomplete="off">
            <button id="scan-ready" class="scan-ready" type="button" hidden>Klik di sini, lalu scan</button>
            <i class="bi bi-upc-scan muted" aria-hidden="true" style="font-size:20px"></i>
            <input id="manual-code" class="mono" maxlength="64" aria-label="Kode part"
                   placeholder="Scan barang yang kembali. Label rusak? Ketik kodenya lalu Enter.">
            <button class="btn" type="submit"><i class="bi bi-arrow-return-left" aria-hidden="true"></i> Kirim</button>
        </form>

        <p id="toast" class="toast" role="alert" hidden></p>

        <div class="card card-flush">
            <div class="table-wrap">
                <table class="scan-table grid">
                    <thead>
                        <tr>
                            <th>Kode part</th>
                            <th>Nama barang</th>
                            <th class="num">Dikirim</th>
                            <th class="num">Diretur sebelumnya</th>
                            <th class="num">Bagus</th>
                            <th class="num">Jelek</th>
                        </tr>
                    </thead>
                    <tbody id="items"></tbody>
                </table>
            </div>
        </div>

        <div class="session-actions">
            <button id="complete" class="btn btn-ok btn-big" type="button" hidden disabled><i class="bi bi-check2-circle" aria-hidden="true"></i> Selesai</button>
        </div>

        <section id="done-panel" class="card return-done" hidden>
            <div>
                <strong>Retur selesai</strong> <span id="done-at" class="muted"></span>
                <div id="zahir-state" class="muted"></div>
            </div>
            <button id="zahir" class="btn" type="button"></button>
        </section>
    </section>
@endsection

@push('scripts')
    <script type="module" src="{{ asset('js/pages/return-session.js') }}"></script>
@endpush
