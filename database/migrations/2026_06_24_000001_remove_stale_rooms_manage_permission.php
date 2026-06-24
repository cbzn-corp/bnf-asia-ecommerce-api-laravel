<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            UPDATE \"Role\"
            SET permissions = array_remove(permissions, 'rooms.manage'),
                \"updatedAt\" = NOW()
            WHERE 'rooms.manage' = ANY(permissions)
        ");
    }

    public function down(): void
    {
        // rooms.manage was removed with the Room entity; do not restore stale permissions.
    }
};
