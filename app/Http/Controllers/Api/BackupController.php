<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BackupService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Menu Backup (superadmin, edisi offline). Rute dijaga middleware edition.offline + can:superadmin. */
class BackupController extends Controller
{
    public function __construct(private readonly BackupService $backup) {}

    public function index(): JsonResponse
    {
        return ApiResponse::ok($this->backup->list());
    }

    public function restore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'string', 'max:255'],
            'confirm' => ['required', 'string'],
        ], ['file.required' => 'Pilih backup dulu']);

        if ($data['confirm'] !== 'PULIHKAN') {
            return ApiResponse::fail('Ketik PULIHKAN untuk konfirmasi', 422);
        }
        if ($this->backup->find($data['file']) === null) {
            return ApiResponse::fail('Backup tidak ditemukan', 422);
        }

        $this->backup->restore($data['file']);

        return ApiResponse::ok(['restored' => true]);
    }

    /** Daftar flashdisk yang bisa dipilih untuk backup. */
    public function disks(): JsonResponse
    {
        return ApiResponse::ok($this->backup->removableDisks());
    }

    /** Salin backup ke flashdisk yang dipilih. */
    public function toDisk(Request $request): JsonResponse
    {
        $drive = trim((string) $request->input('drive'));
        if ($drive === '') {
            return ApiResponse::fail('Tidak ada flashdisk', 422);
        }

        return ApiResponse::ok(['copied' => $this->backup->copyToDisk($drive)]);
    }

    /**
     * Unduh backup terbaru untuk komputer scan. Dijaga kode pemasangan (header X-Pairing-Code),
     * bukan login, karena dipanggil terjadwal oleh komputer scan. Batas laju diatur di rute.
     */
    public function latest(Request $request): BinaryFileResponse
    {
        $expected = (string) config('app.pairing_code');
        $given = (string) $request->header('X-Pairing-Code', '');
        abort_unless($expected !== '' && hash_equals($expected, $given), 403);

        $path = $this->backup->latest();
        abort_if($path === null, 404);

        return response()->download($path, 'faktur-terbaru.sqlite');
    }
}
