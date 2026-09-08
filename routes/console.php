<?php

use App\Jobs\CloseAuction;
use App\Models\Auction;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    Auction::query()
        ->where('status', 'live')
        ->where('ends_at', '<=', now())
        ->select('id')
        ->cursor()
        ->each(fn (Auction $auction) => CloseAuction::dispatch($auction->id));
})->name('close-ended-auctions')->everyMinute()->withoutOverlapping()->onOneServer();

Schedule::command('checkout:reconcile-payments')->everyMinute()->withoutOverlapping()->onOneServer();

Schedule::command('seo:check-catalog')->dailyAt('01:45')->timezone('Asia/Colombo')
    ->environments('production')->when(fn (): bool => (bool) config('seo-monitoring.enabled'))
    ->withoutOverlapping(30)->onOneServer()->runInBackground();

Schedule::command('seo:check-merchant')->dailyAt('03:30')->timezone('Asia/Colombo')
    ->environments('production')->when(fn (): bool => (bool) config('seo-monitoring.enabled'))
    ->withoutOverlapping(30)->onOneServer()->runInBackground();
