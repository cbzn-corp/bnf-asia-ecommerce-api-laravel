<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('referrals:backfill-commissions', function () {
  /** @var \App\Services\ReferralsService $service */
  $service = app(\App\Services\ReferralsService::class);
  $result = $service->backfillCommissions();
  $this->info("Processed {$result['processed']} paid referred orders, created {$result['created']} commission rows.");
})->purpose('Backfill referral commissions for paid orders missing commission rows');
