@extends('layouts.app')

@section('title', 'Gudang')

@section('content')
    <div class="row" style="justify-content:space-between;align-items:center">
        <h1>Sesi faktur</h1>
        <div class="tabs" role="tablist" aria-label="Status sesi">
            <button type="button" role="tab" class="tab" data-status="open" aria-selected="true">Perlu di-scan</button>
            <button type="button" role="tab" class="tab" data-status="done" aria-selected="false">Selesai</button>
        </div>
    </div>

    <div class="field">
        <label for="search">Cari no. faktur atau nama penerima</label>
        <input id="search" type="search" maxlength="150" autocomplete="off">
    </div>

    <p id="status" class="msg msg-bad" hidden></p>
    <div id="list" class="session-list"></div>
    <p id="empty" class="muted" hidden>Belum ada faktur di sini.</p>
@endsection

@push('scripts')
    <script type="module" src="{{ asset('js/pages/warehouse-index.js') }}"></script>
@endpush
