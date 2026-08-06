<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 16)->nullable()->unique()->after('google_id');
            $table->foreignId('referred_by_user_id')
                ->nullable()
                ->after('referral_code')
                ->constrained('users')
                ->nullOnDelete();
        });

        // Existing members need a unique shareable code before the column is required.
        DB::table('users')
            ->whereNull('referral_code')
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $referralCode = null;

                    for ($attempt = 0; $attempt < 20; $attempt++) {
                        $candidate = Str::upper(Str::random(8));

                        if (! DB::table('users')->where('referral_code', $candidate)->exists()) {
                            $referralCode = $candidate;
                            break;
                        }
                    }

                    if ($referralCode === null) {
                        $referralCode = Str::upper(Str::random(12));
                    }

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['referral_code' => $referralCode]);
                }
            });

        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 16)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by_user_id');
            $table->dropUnique(['referral_code']);
            $table->dropColumn('referral_code');
        });
    }
};
