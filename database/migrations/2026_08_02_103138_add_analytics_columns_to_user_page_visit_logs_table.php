<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_page_visit_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('user_page_visit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->string('device_type')->nullable()->after('referrer');
            $table->string('traffic_source')->nullable()->after('device_type');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_page_visit_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('user_page_visit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->dropColumn(['device_type', 'traffic_source']);

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }
};
