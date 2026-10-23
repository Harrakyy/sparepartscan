<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['line_no', 'part_code', 'part_name', 'qty', 'picked_qty', 'qc_qty'];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'qty' => 'integer',
            'picked_qty' => 'integer',
            'qc_qty' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
