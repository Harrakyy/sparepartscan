<?php

use App\Support\Edition;
use Illuminate\Support\Facades\Schedule;

Schedule::command('invoices:purge-files')->dailyAt('02:00')->timezone('Asia/Jakarta');

// Backup otomatis hanya di edisi offline (database di komputer master MGI).
if (Edition::isOffline()) {
    Schedule::command('app:backup perjam')->hourly();
    Schedule::command('app:backup harian')->dailyAt('23:00')->timezone('Asia/Jakarta');
}
