<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReturnScan extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['invoice_item_id', 'code', 'condition', 'result', 'manual'];

    protected function casts(): array
    {
        return ['manual' => 'boolean', 'created_at' => 'datetime'];
    }
}
