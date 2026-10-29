<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    public const OPEN_STATUSES = ['waiting', 'picking'];

    public const DONE_STATUSES = ['picked', 'qc', 'qc_done'];

    protected $fillable = [
        'number', 'customer_name', 'invoice_date', 'source_type', 'file_path',
        'file_purged_at', 'status', 'picked_at', 'qc_done_at', 'printed_at',
    ];

    protected $hidden = ['file_path'];

    protected function casts(): array
    {
        return [
            'file_purged_at' => 'datetime',
            'picked_at' => 'datetime',
            'qc_done_at' => 'datetime',
            'printed_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('line_no');
    }

    public function scanEvents(): HasMany
    {
        return $this->hasMany(ScanEvent::class);
    }

    public function printLogs(): HasMany
    {
        return $this->hasMany(PrintLog::class);
    }

    public function goodsReturns(): HasMany
    {
        return $this->hasMany(GoodsReturn::class);
    }

    public function qtyAdjustments(): HasMany
    {
        return $this->hasMany(QtyAdjustment::class);
    }
}
