<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · Verifikasi Faktur</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
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

        <form class="card" method="POST" action="{{ url('/login') }}" novalidate>
            @csrf
            <h1>Masuk</h1>
            @if ($errors->any())
                <p class="msg msg-bad" role="alert">{{ $errors->first() }}</p>
            @endif
            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
            </div>
            <label class="login-remember">
                <input name="remember" type="checkbox" value="1" @checked(old('remember'))>
                Ingat saya
            </label>
            <button class="btn btn-primary btn-big" type="submit"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Masuk</button>
            <p class="muted login-help">Lupa password? Hubungi superadmin untuk dibuatkan password baru.</p>
        </form>
    </main>
</body>
</html>
