<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jumlah keluar setelah diedit superadmin (retur). Null = sama dengan jumlah faktur.
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->unsignedInteger('out_qty')->nullable()->after('qty');
        });

        Schema::create('qty_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('from_qty');
            $table->unsignedInteger('to_qty');
            $table->string('note', 255);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['invoice_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qty_adjustments');
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('out_qty');
        });
    }
};
