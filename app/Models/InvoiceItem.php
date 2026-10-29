<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['line_no', 'part_code', 'part_name', 'qty', 'out_qty', 'picked_qty', 'qc_qty'];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'qty' => 'integer',
            'out_qty' => 'integer',
            'picked_qty' => 'integer',
            'qc_qty' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** Jumlah yang dihitung keluar gudang: jumlah faktur, atau hasil edit superadmin setelah retur. */
    public function outQty(): int
    {
        return $this->out_qty ?? $this->qty;
    }
}
