<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->string('part_code', 64);
            $table->string('part_name');
            $table->unsignedInteger('qty');
            $table->unsignedInteger('picked_qty')->default(0);
            $table->unsignedInteger('qc_qty')->default(0);
            $table->unique(['invoice_id', 'part_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
