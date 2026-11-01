<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('invoices:generate')->dailyAt('06:00')->timezone('Asia/Jakarta')->withoutOverlapping();
Schedule::command('invoices:mark-overdue')->dailyAt('00:10')->timezone('Asia/Jakarta')->withoutOverlapping();
Schedule::command('invoices:remind')->dailyAt('08:00')->timezone('Asia/Jakarta')->withoutOverlapping();
