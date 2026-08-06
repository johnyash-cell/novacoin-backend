<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_deposits', function (Blueprint $table) {
            // Nullable so existing local rows (if any) do not block the alter; new deposits always set a value on create.
            $table->string('reference_number')->nullable()->after('id')->unique();
        });
    }

    public function down(): void
    {
        Schema::table('wallet_deposits', function (Blueprint $table) {
            $table->dropUnique(['reference_number']);
            $table->dropColumn('reference_number');
        });
    }
};
