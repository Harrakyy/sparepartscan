<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->string('customer_name', 150);
            $table->string('invoice_date', 60)->nullable();
            $table->enum('source_type', ['pdf', 'photo']);
            $table->string('file_path')->nullable();
            $table->timestamp('file_purged_at')->nullable();
            $table->enum('status', ['waiting', 'picking', 'picked', 'qc', 'qc_done'])->default('waiting')->index();
            $table->timestamp('picked_at')->nullable()->index();
            $table->timestamp('qc_done_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
