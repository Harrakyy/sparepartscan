<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · Verifikasi Faktur</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="@yield('body-class')">
    <header class="topbar">
        <a href="{{ url('/') }}" class="brand">
            <span class="brand-logo"><i class="bi bi-upc-scan" aria-hidden="true"></i></span>
            <span class="brand-text">
                <span class="brand-name">Verifikasi Faktur</span><br>
                <span class="brand-sub">Gudang Sparepart</span>
            </span>
        </a>
        <nav class="nav" aria-label="Menu utama">
            <a href="{{ url('/faktur/baru') }}" @if (request()->is('faktur*')) aria-current="page" @endif>
                <i class="bi bi-file-earmark-arrow-up" aria-hidden="true"></i><span>Faktur baru</span>
            </a>
            <a href="{{ url('/gudang') }}" @if (request()->is('gudang*')) aria-current="page" @endif>
                <i class="bi bi-box-seam" aria-hidden="true"></i><span>Gudang</span>
            </a>
            <a href="{{ url('/laporan') }}" @if (request()->is('laporan*')) aria-current="page" @endif>
                <i class="bi bi-table" aria-hidden="true"></i><span>Laporan</span>
            </a>
        </nav>
    </header>
    <main class="page">
        @yield('content')
    </main>
    @stack('scripts')
</body>
</html>
