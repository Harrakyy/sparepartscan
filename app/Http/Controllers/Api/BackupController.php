<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BackupService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
