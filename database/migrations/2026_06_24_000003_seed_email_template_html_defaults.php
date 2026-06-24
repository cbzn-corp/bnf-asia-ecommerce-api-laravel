<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = \App\Support\Email\EmailTemplateDefaults::allHtml();
        $now = now();

        foreach ($defaults as $key => $bodyHtml) {
            DB::table('EmailTemplate')
                ->where('key', $key)
                ->where(function ($query) {
                    $query->whereNull('bodyHtml')->orWhere('bodyHtml', '');
                })
                ->update([
                    'bodyHtml' => $bodyHtml,
                    'updatedAt' => $now,
                ]);
        }
    }

    public function down(): void
    {
        // Non-destructive: keep customized HTML in place.
    }
};
