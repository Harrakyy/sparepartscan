<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Login memakai username (mis. "budi"), bukan email. Data lama: username diambil dari bagian sebelum "@"
 * (huruf kecil, hanya a-z 0-9 . _ -); terlalu pendek → user<id>; bentrok → akhiran -2, -3, ...
 */
return new class extends Migration
{
    private const MAX = 30;

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', self::MAX)->nullable()->after('name');
        });

        $taken = [];
        foreach (DB::table('users')->orderBy('id')->get(['id', 'email']) as $row) {
            $username = $this->uniqueName($this->baseName((string) $row->email, $row->id), $taken);
            $taken[$username] = true;
            DB::table('users')->where('id', $row->id)->update(['username' => $username]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email', 'email_verified_at']);
            $table->string('username', self::MAX)->nullable(false)->change();
            $table->unique('username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->after('name');
            $table->timestamp('email_verified_at')->nullable()->after('email');
        });
        foreach (DB::table('users')->get(['id', 'username']) as $row) {
            DB::table('users')->where('id', $row->id)->update(['email' => $row->username.'@lokal.invalid']);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('username');
            $table->string('email')->nullable(false)->change();
            $table->unique('email');
        });
    }

    private function baseName(string $email, int $id): string
    {
        $base = preg_replace('/[^a-z0-9._-]/', '', strtolower(strstr($email, '@', true) ?: $email));

        return strlen($base) < 3 ? 'user'.$id : substr($base, 0, self::MAX);
    }

    /** @param array<string, bool> $taken */
    private function uniqueName(string $base, array $taken): string
    {
        $name = $base;
        for ($n = 2; isset($taken[$name]); $n++) {
            $suffix = '-'.$n;
            $name = substr($base, 0, self::MAX - strlen($suffix)).$suffix;
        }

        return $name;
    }
};
