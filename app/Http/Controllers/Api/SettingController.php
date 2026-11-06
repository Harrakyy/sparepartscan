<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Setelan yang bisa diubah superadmin (edisi offline): pendaftaran mandiri petugas. */
class SettingController extends Controller
{
    public function show(): JsonResponse
    {
        return ApiResponse::ok($this->current());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['allow_registration' => ['required', 'boolean']], ['allow_registration.*' => 'Setelan tidak valid']);
        Setting::set('allow_registration', $data['allow_registration'] ? '1' : '0');

        return ApiResponse::ok($this->current());
    }

    private function current(): array
    {
        return ['allow_registration' => Setting::get('allow_registration', '1') === '1'];
    }
}
