<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['invoice_item_id', 'stage', 'code', 'result', 'manual'];

    protected function casts(): array
    {
        return ['manual' => 'boolean', 'created_at' => 'datetime'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
