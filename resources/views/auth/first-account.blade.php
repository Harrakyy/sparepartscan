<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Akun pertama · Verifikasi Faktur</title>
    <link rel="stylesheet" href="{{ asset('vendor/inter/inter.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="login-page">
    <main class="login-box">
        <div class="brand login-brand">
            <span class="brand-logo"><i class="bi bi-person-gear" aria-hidden="true"></i></span>
            <span class="brand-text">
                <span class="brand-name">Verifikasi Faktur</span><br>
                <span class="brand-sub">Gudang Sparepart</span>
            </span>
        </div>

        <form class="card" method="POST" action="{{ url('/akun-pertama') }}" novalidate>
            @csrf
            <h1>Buat akun superadmin</h1>
            <p class="muted">Akun pertama ini yang mengelola aplikasi: pengguna, backup, dan hapus/ubah faktur. Simpan username dan password-nya baik-baik.</p>
            @include('auth.partials.account-fields')
            <button class="btn btn-primary btn-big" type="submit"><i class="bi bi-check2-circle" aria-hidden="true"></i> Buat akun &amp; masuk</button>
        </form>
    </main>
</body>
</html>
