<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('stage', ['pick', 'qc']);
            $table->string('code', 64);
            $table->enum('result', ['ok', 'over_qty', 'not_in_invoice']);
            $table->boolean('manual')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['invoice_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_events');
    }
};
