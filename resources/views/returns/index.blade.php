@extends('layouts.app')

@section('title', 'Retur')

@section('content')
    <div class="page-head">
        <div>
            <h1>Retur barang</h1>
            <p class="page-sub">Barang yang dikembalikan pelanggan, dipisah bagus dan jelek.</p>
        </div>
        <a href="{{ url('/retur/baru') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Retur baru</a>
    </div>

    <section class="card card-flush">
        <div class="card-head">
            <div class="tabs" role="tablist" aria-label="Status retur">
                <button type="button" role="tab" class="tab" data-tab="open" aria-selected="true">
                    <i class="bi bi-upc-scan" aria-hidden="true"></i> Sedang dicek <span id="count-open" class="count-pill">0</span>
                </button>
                <button type="button" role="tab" class="tab" data-tab="done" aria-selected="false">
                    <i class="bi bi-check2-circle" aria-hidden="true"></i> Selesai <span id="count-done" class="count-pill">0</span>
                </button>
                <button type="button" role="tab" class="tab" data-tab="zahir_pending" aria-selected="false">
                    <i class="bi bi-hourglass-split" aria-hidden="true"></i> Belum di Zahir <span id="count-zahir_pending" class="count-pill">0</span>
                </button>
            </div>
        </div>

        <p id="status" class="msg msg-bad" style="margin:16px" hidden></p>
        <div class="table-wrap">
            <table class="grid">
                <thead>
                    <tr>
                        <th class="rownum">#</th>
                        <th>Tanggal</th>
                        <th>No. faktur</th>
                        <th>Kepada</th>
                        <th class="num">Bagus</th>
                        <th class="num">Jelek</th>
                        <th>Zahir</th>
                    </tr>
                </thead>
                <tbody id="list"></tbody>
            </table>
        </div>
        <p id="empty" class="muted" style="margin:0;padding:28px;text-align:center" hidden>
            <i class="bi bi-inbox" aria-hidden="true"></i> Belum ada retur di sini.
        </p>
    </section>
@endsection

@push('scripts')
    <script type="module" src="{{ asset('js/pages/return-index.js') }}"></script>
@endpush
