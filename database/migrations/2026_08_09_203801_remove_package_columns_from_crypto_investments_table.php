<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crypto_investments')) {
            return;
        }

        Schema::table('crypto_investments', function (Blueprint $table): void {
            if (Schema::hasColumn('crypto_investments', 'crypto_investment_package_id')) {
                $table->dropConstrainedForeignId('crypto_investment_package_id');
            }

            if (Schema::hasColumn('crypto_investments', 'package_name')) {
                $table->dropColumn('package_name');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('crypto_investments')) {
            return;
        }

        Schema::table('crypto_investments', function (Blueprint $table): void {
            if (! Schema::hasColumn('crypto_investments', 'package_name')) {
                $table->string('package_name')->nullable()->after('user_id');
            }

            if (! Schema::hasColumn('crypto_investments', 'crypto_investment_package_id')) {
                $table->foreignId('crypto_investment_package_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('crypto_investment_packages')
                    ->restrictOnDelete();
            }
        });
    }
};
