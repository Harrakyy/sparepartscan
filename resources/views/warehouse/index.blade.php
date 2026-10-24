@extends('layouts.app')

@section('title', 'Gudang')

@section('content')
    <div class="page-head">
        <div>
            <h1>Sesi faktur</h1>
            <p class="page-sub">Pilih faktur untuk mulai scan barang.</p>
        </div>
    </div>

    <section class="card card-flush">
        <div class="card-head">
            <div class="tabs" role="tablist" aria-label="Status sesi">
                <button type="button" role="tab" class="tab" data-status="open" aria-selected="true">
                    <i class="bi bi-upc-scan" aria-hidden="true"></i> Perlu di-scan <span id="count-open" class="count-pill">0</span>
                </button>
                <button type="button" role="tab" class="tab" data-status="done" aria-selected="false">
                    <i class="bi bi-check2-circle" aria-hidden="true"></i> Selesai <span id="count-done" class="count-pill">0</span>
                </button>
            </div>
            <label class="search">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input id="search" type="search" maxlength="150" autocomplete="off" placeholder="Cari no. faktur atau nama penerima" aria-label="Cari no. faktur atau nama penerima">
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
                        <th>Diunggah</th>
                        <th>Status</th>
                        <th class="num">Ter-scan</th>
                    </tr>
                </thead>
                <tbody id="list"></tbody>
            </table>
        </div>
        <p id="empty" class="muted" style="margin:0;padding:28px;text-align:center" hidden>
            <i class="bi bi-inbox" aria-hidden="true"></i> Belum ada faktur di sini.
        </p>
    </section>
@endsection

@push('scripts')
    <script type="module" src="{{ asset('js/pages/warehouse-index.js') }}"></script>
@endpush
