@extends('layouts.app')

@section('title', 'Faktur baru')

@section('content')
    <div class="page-head">
        <div>
            <h1>Faktur baru</h1>
            <p class="page-sub">Unggah PDF dari Zahir (Microsoft Print to PDF) atau foto faktur. Hasil bacaan dicek dulu sebelum disimpan.</p>
        </div>
    </div>

    <label id="dropzone" class="dropzone" for="file">
        <i class="bi bi-file-earmark-arrow-up" aria-hidden="true"></i><br>
        <strong>Pilih file faktur</strong> atau tarik ke sini
        <div class="muted">PDF, JPG, atau PNG · maks. 10 MB</div>
        <input id="file" type="file" accept="application/pdf,image/jpeg,image/png" style="display:none">
    </label>

    <div id="reading" class="card" hidden>
        <div id="reading-label">Membaca faktur…</div>
        <div class="progress"><span id="reading-bar"></span></div>
    </div>

    <p id="status" class="msg" hidden></p>

    <section id="confirm" class="split" hidden>
        <div class="card">
            <h2>Faktur asli</h2>
            <img id="preview-img" class="preview" alt="Pratinjau faktur" hidden>
            <canvas id="preview-canvas" class="preview" hidden></canvas>
        </div>

        <form id="invoice-form" class="card" autocomplete="off" novalidate>
            <h2>Hasil bacaan — cek dan perbaiki bila perlu</h2>
            <div id="warnings" class="msg msg-warn" hidden></div>
            <div class="row">
                <div class="field">
                    <label for="number">No. faktur</label>
                    <input id="number" class="mono" inputmode="numeric" maxlength="32" required>
                </div>
                <div class="field" style="flex:2">
                    <label for="customer">Kepada (penerima)</label>
                    <input id="customer" maxlength="150" required>
                </div>
            </div>
            <div class="field">
                <label for="date">Tanggal (opsional)</label>
                <input id="date" maxlength="60">
            </div>
            <div class="table-wrap">
                <table class="grid">
                    <thead>
                        <tr><th style="width:28%">Kode part</th><th>Nama barang</th><th class="num" style="width:90px">Jumlah</th><th style="width:48px"></th></tr>
                    </thead>
                    <tbody id="items"></tbody>
                </table>
            </div>
            <div class="row" style="justify-content:space-between;align-items:center">
                <button id="add-row" class="btn" type="button">+ Tambah baris</button>
                <span id="total" class="muted"></span>
            </div>
            <div class="row" style="justify-content:flex-end">
                <button id="reset" class="btn" type="button">Batal</button>
                <button id="save" class="btn btn-primary" type="submit">Simpan faktur</button>
            </div>
        </form>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/tesseract/tesseract.min.js') }}"></script>
    <script type="module" src="{{ asset('js/pages/invoice-create.js') }}"></script>
@endpush
