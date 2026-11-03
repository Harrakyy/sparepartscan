<?php

namespace App\Http\Controllers;

use App\Services\License\LicenseFile;
use App\Services\License\MachineId;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/** Halaman aktivasi lisensi (edisi offline). Selalu terbuka; tak perlu login. */
class LicenseController extends Controller
{
    private const PESAN = [
        'machine' => 'Lisensi ini untuk komputer lain. Minta lisensi yang dibuat untuk ID komputer di bawah.',
        'expired' => 'Lisensi sudah kedaluwarsa. Minta lisensi baru ke Ashira.',
        'signature' => 'Tanda tangan lisensi tidak sah. Pastikan file lisensi tidak berubah.',
    ];

    public function show(MachineId $machine): View
    {
        return view('license.activate', ['machineId' => $machine->current()]);
    }

    public function store(Request $request, LicenseFile $license, MachineId $machine): RedirectResponse
    {
        $content = $this->isi($request);
        if ($content === null) {
            return back()->withErrors(['lisensi' => 'Pilih file lisensi (.lic) atau tempel isinya.']);
        }

        try {
            $parsed = $license->parse($content);
        } catch (\Throwable) {
            return back()->withErrors(['lisensi' => 'File lisensi tidak bisa dibaca.']);
        }

        $check = $license->valid($parsed, $machine->current(), now());
        if (! $check['ok']) {
            return back()->withErrors(['lisensi' => self::PESAN[$check['reason']] ?? 'Lisensi tidak sah.']);
        }

        file_put_contents($license->path(), $content);
        Cache::forget('license.ok');

        return redirect('/')->with('status', 'Lisensi aktif. Aplikasi siap dipakai.');
    }

    /** Ambil isi lisensi dari file unggahan atau teks yang ditempel. */
    private function isi(Request $request): ?string
    {
        if ($request->hasFile('file')) {
            return (string) file_get_contents($request->file('file')->getRealPath());
        }
        $teks = trim((string) $request->input('teks'));

        return $teks !== '' ? $teks : null;
    }
}
