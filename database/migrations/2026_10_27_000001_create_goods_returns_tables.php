<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('note', 500)->nullable();
            $table->enum('status', ['open', 'done'])->default('open')->index();
            $table->timestamp('done_at')->nullable();
            $table->timestamp('zahir_entered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('goods_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_item_id')->constrained()->cascadeOnDelete();
            $table->enum('condition', ['good', 'bad']);
            $table->unsignedInteger('qty');
            $table->timestamps();
            $table->unique(['goods_return_id', 'invoice_item_id', 'condition']);
        });

        Schema::create('goods_return_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 64);
            $table->enum('condition', ['good', 'bad']);
            $table->enum('result', ['ok', 'over_qty', 'not_in_invoice']);
            $table->boolean('manual')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['goods_return_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_return_scans');
        Schema::dropIfExists('goods_return_items');
        Schema::dropIfExists('goods_returns');
    }
};
