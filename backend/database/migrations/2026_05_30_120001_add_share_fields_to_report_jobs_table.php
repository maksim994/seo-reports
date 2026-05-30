<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_jobs', function (Blueprint $table) {
            $table->string('share_token', 64)->nullable()->unique()->after('finished_at');
            $table->boolean('share_enabled')->default(false)->after('share_token');
            $table->timestamp('share_expires_at')->nullable()->after('share_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('report_jobs', function (Blueprint $table) {
            $table->dropColumn(['share_token', 'share_enabled', 'share_expires_at']);
        });
    }
};
