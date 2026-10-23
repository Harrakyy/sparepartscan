<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('invoices:purge-files')->dailyAt('02:00')->timezone('Asia/Jakarta');
