<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · Verifikasi Faktur</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="@yield('body-class')">
    <header class="topbar">
        <span class="brand">Verifikasi Faktur</span>
        <nav class="nav" aria-label="Menu utama">
            <a href="{{ url('/faktur/baru') }}" @if (request()->is('faktur*')) aria-current="page" @endif>Faktur baru</a>
            <a href="{{ url('/gudang') }}" @if (request()->is('gudang*')) aria-current="page" @endif>Gudang</a>
            <a href="{{ url('/laporan') }}" @if (request()->is('laporan*')) aria-current="page" @endif>Laporan</a>
        </nav>
    </header>
    <main class="page">
        @yield('content')
    </main>
    @stack('scripts')
</body>
</html>
