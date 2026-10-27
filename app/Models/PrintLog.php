<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Satu kali tombol Cetak ditekan untuk sebuah faktur. */
class PrintLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
