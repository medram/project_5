<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $freePlanId = DB::table('plans')->where('code', 'free')->value('id');

        if ($freePlanId === null) {
            throw new RuntimeException('The free plan must exist before user subscriptions are backfilled.');
        }

        DB::table('users')->orderBy('id')->chunkById(100, function ($users) use ($freePlanId): void {
            foreach ($users as $user) {
                $now = now();

                DB::table('user_subscriptions')->insertOrIgnore([
                    'user_id' => $user->id,
                    'plan_id' => $freePlanId,
                    'status' => 'active',
                    'source' => 'manual',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        // The preceding migration drops this table when rolling back the slice.
    }
};
