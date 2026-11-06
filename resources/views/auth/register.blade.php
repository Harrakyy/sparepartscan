<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar · Verifikasi Faktur</title>
    <link rel="stylesheet" href="{{ asset('vendor/inter/inter.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="login-page">
    <main class="login-box">
        <div class="brand login-brand">
            <span class="brand-logo"><i class="bi bi-upc-scan" aria-hidden="true"></i></span>
            <span class="brand-text">
                <span class="brand-name">Verifikasi Faktur</span><br>
                <span class="brand-sub">Gudang Sparepart</span>
            </span>
        </div>

        <form class="card" method="POST" action="{{ url('/daftar') }}" novalidate>
            @csrf
            <h1>Daftar petugas</h1>
            <p class="muted">Buat akun untuk scan barang di gudang.</p>
            @include('auth.partials.account-fields')
            <button class="btn btn-primary btn-big" type="submit"><i class="bi bi-person-plus" aria-hidden="true"></i> Daftar &amp; masuk</button>
            <p class="muted login-help">Sudah punya akun? <a href="{{ url('/login') }}">Masuk</a></p>
        </form>
    </main>
</body>
</html>
