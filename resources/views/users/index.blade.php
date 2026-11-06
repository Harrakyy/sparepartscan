@extends('layouts.app')

@section('title', 'Pengguna')

@section('content')
    <div class="page-head">
        <div>
            <h1>Pengguna</h1>
            <p class="page-sub">Hanya akun di sini yang bisa masuk. Superadmin bisa menghapus faktur, mengedit jumlah keluar, dan mengelola pengguna.</p>
        </div>
    </div>

    @if (\App\Support\Edition::isOffline())
        <label class="card row" for="allow-registration">
            <input id="allow-registration" type="checkbox">
            <span><strong>Izinkan petugas mendaftar sendiri</strong><br>
                <span class="muted">Tombol "Daftar" di halaman masuk. Akun baru menjadi petugas gudang; matikan bila tidak dipakai.</span></span>
        </label>
    @endif

    <form id="add-form" class="card row" autocomplete="off" novalidate>
        <div class="field">
            <label for="name">Nama</label>
            <input id="name" maxlength="100" required>
        </div>
        <div class="field">
            <label for="username">Username</label>
            <input id="username" maxlength="30" autocapitalize="none" spellcheck="false" placeholder="mis. budi" required>
        </div>
        <div class="field">
            <label for="role">Peran</label>
            <select id="role">
                <option value="admin">Admin</option>
                <option value="superadmin">Superadmin</option>
            </select>
        </div>
        <div class="field">
            <label for="password">Password (min. 8)</label>
            <input id="password" type="password" minlength="8" autocomplete="new-password" required>
        </div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-person-plus" aria-hidden="true"></i> Tambah</button>
    </form>

    <p id="status" class="msg" hidden></p>

    <section class="card card-flush">
        <div class="table-wrap">
            <table class="grid">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Peran</th>
                        <th>Status</th>
                        <th>Masuk terakhir</th>
                        <th class="num">Aksi</th>
                    </tr>
                </thead>
                <tbody id="list"></tbody>
            </table>
        </div>
    </section>
@endsection

@push('scripts')
    <script>window.currentUserId = {{ auth()->id() }};</script>
    <script type="module" src="{{ asset('js/pages/users.js') }}"></script>
@endpush
