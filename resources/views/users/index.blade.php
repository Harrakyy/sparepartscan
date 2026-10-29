@extends('layouts.app')

@section('title', 'Pengguna')

@section('content')
    <div class="page-head">
        <div>
            <h1>Pengguna</h1>
            <p class="page-sub">Hanya akun di sini yang bisa masuk. Superadmin bisa menghapus faktur, mengedit jumlah keluar, dan mengelola pengguna.</p>
        </div>
    </div>

    <form id="add-form" class="card row" autocomplete="off" novalidate>
        <div class="field">
            <label for="name">Nama</label>
            <input id="name" maxlength="100" required>
        </div>
        <div class="field">
            <label for="email">Email</label>
            <input id="email" type="email" maxlength="255" required>
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
                        <th>Email</th>
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
