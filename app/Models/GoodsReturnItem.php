<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Jumlah satu barang faktur yang kembali, per kondisi (good/bad), dalam satu retur. */
class GoodsReturnItem extends Model
{
    protected $fillable = ['invoice_item_id', 'condition', 'qty'];

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }
}
