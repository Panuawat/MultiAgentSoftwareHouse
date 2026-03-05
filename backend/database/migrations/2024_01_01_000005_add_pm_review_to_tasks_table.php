<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('pm_review_enabled')->default(false)->after('retry_count');
            $table->json('pm_messages')->nullable()->after('pm_review_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['pm_review_enabled', 'pm_messages']);
        });
    }
};
