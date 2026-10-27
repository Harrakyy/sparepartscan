<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Satu kejadian retur barang dari pelanggan. Bukan `Return` karena itu kata kunci PHP. */
class GoodsReturn extends Model
{
    protected $fillable = ['note', 'status', 'done_at', 'zahir_entered_at'];

    protected function casts(): array
    {
        return ['done_at' => 'datetime', 'zahir_entered_at' => 'datetime'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReturnItem::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(GoodsReturnScan::class);
    }
}
