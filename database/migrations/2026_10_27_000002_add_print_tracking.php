<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Waktu cetak pertama: barang dianggap keluar sejak struk pertama kali dicetak.
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('printed_at')->nullable()->index()->after('qc_done_at');
        });

        Schema::create('print_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['invoice_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_logs');
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['printed_at']);
            $table->dropColumn('printed_at');
        });
    }
};
