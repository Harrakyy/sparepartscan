@extends('layouts.app')

@section('title', 'Backup')

@section('content')
    <div class="page-head">
        <div>
            <h1>Backup</h1>
            <p class="page-sub">Backup berisi database (daftar faktur dan scan), bukan file PDF/foto. Dibuat otomatis tiap jam dan tiap malam; di sini bisa disalin ke flashdisk atau dipulihkan.</p>
        </div>
    </div>

    <p id="status" class="msg" hidden></p>

    <section class="card">
        <h2 class="card-title"><i class="bi bi-usb-drive" aria-hidden="true"></i> Backup ke flashdisk</h2>
        <p class="muted">Terakhir ke flashdisk: <span id="last-disk" data-iso="{{ $lastDisk }}">{{ $lastDisk ? '' : 'Belum pernah' }}</span></p>
        <div class="row">
            <div class="field">
                <label for="disk">Flashdisk</label>
                <select id="disk"><option value="">Memuat…</option></select>
            </div>
            <button id="muat-disk" class="btn" type="button" title="Muat ulang daftar flashdisk"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>
            <button id="ke-disk" class="btn btn-primary" type="button"><i class="bi bi-download" aria-hidden="true"></i> Backup ke flashdisk</button>
        </div>
    </section>

    <section class="card card-flush">
        <div class="table-wrap">
            <table class="grid">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Jenis</th>
                        <th class="num">Jumlah faktur</th>
                        <th class="num">Ukuran</th>
                        <th class="num">Aksi</th>
                    </tr>
                </thead>
                <tbody id="list"></tbody>
            </table>
        </div>
    </section>
@endsection

@push('scripts')
    <script type="module" src="{{ asset('js/pages/backup.js') }}"></script>
@endpush
