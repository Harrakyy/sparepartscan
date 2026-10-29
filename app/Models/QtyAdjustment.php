<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu perubahan jumlah keluar satu barang oleh superadmin (misalnya karena retur). */
class QtyAdjustment extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['invoice_item_id', 'user_id', 'from_qty', 'to_qty', 'note'];

    protected function casts(): array
    {
        return ['from_qty' => 'integer', 'to_qty' => 'integer', 'created_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class, 'invoice_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
