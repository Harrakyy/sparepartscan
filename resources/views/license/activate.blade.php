<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aktivasi · Verifikasi Faktur</title>
    <link rel="stylesheet" href="{{ asset('vendor/inter/inter.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="login-page">
    <main class="login-box">
        <div class="brand login-brand">
            <span class="brand-logo"><i class="bi bi-key" aria-hidden="true"></i></span>
            <span class="brand-text">
                <span class="brand-name">Aktivasi</span><br>
                <span class="brand-sub">Verifikasi Faktur</span>
            </span>
        </div>

        <form class="card" method="POST" action="{{ url('/aktivasi') }}" enctype="multipart/form-data" novalidate>
            @csrf
            <h1>Aktivasi komputer</h1>
            <p class="muted">Aplikasi ini terkunci ke komputer ini. Kirim <strong>ID komputer</strong> di bawah ke Ashira, lalu unggah file lisensi (<code>license.lic</code>) yang diberikan.</p>

            @if ($errors->any())
                <p class="msg msg-bad" role="alert">{{ $errors->first() }}</p>
            @endif

            <div class="field">
                <label for="machine-id">ID komputer</label>
                @if ($machineId)
                    <input id="machine-id" value="{{ $machineId }}" readonly onclick="this.select()">
                @else
                    <p class="msg msg-bad">ID komputer tidak terbaca. Tutup, lalu jalankan aplikasi sebagai administrator.</p>
                @endif
            </div>

            <div class="field">
                <label for="file">File lisensi (.lic)</label>
                <input id="file" name="file" type="file" accept=".lic,application/json,text/plain">
            </div>

            <div class="field">
                <label for="teks">atau tempel isi lisensi</label>
                <textarea id="teks" name="teks" rows="4" placeholder="{ &quot;payload&quot;: … }"></textarea>
            </div>

            <button class="btn btn-primary btn-big" type="submit"><i class="bi bi-unlock" aria-hidden="true"></i> Aktifkan</button>
        </form>
    </main>
</body>
</html>
