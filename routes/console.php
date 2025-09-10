<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('angler-hub:status', function () {
    $this->info('AnglerHub Status Check');
    $this->line('================');
    
    // Check database connection
    try {
        DB::connection()->getPdo();
        $this->info('✓ Database connection: OK');
    } catch (Exception $e) {
        $this->error('✗ Database connection: Failed');
        $this->error($e->getMessage());
    }
    
    // Check essential tables
    $tables = ['users', 'catches', 'goals', 'weather_logs', 'personal_access_tokens'];
    foreach ($tables as $table) {
        if (Schema::hasTable($table)) {
            $this->info("✓ Table {$table}: EXISTS");
        } else {
            $this->error("✗ Table {$table}: MISSING");
        }
    }
    
    // Check user count
    $userCount = \App\Models\User::count();
    $this->info("Users registered: {$userCount}");
    
    $this->line('================');
    $this->info('AnglerHub ready to cast off! 🎣');
    
})->purpose('Check AnglerHub system status');