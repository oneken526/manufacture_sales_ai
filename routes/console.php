<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 日次バックアップ（NFR-031: システムは日次バックアップを実施しなければならない）
// サーバー側の cron 設定: * * * * * php artisan schedule:run >> /dev/null 2>&1
Schedule::command('backup:run --keep=7')->daily()->at('02:00');
