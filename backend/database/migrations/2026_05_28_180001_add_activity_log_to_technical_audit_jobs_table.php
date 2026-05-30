<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technical_audit_jobs', function (Blueprint $table) {
            $table->json('activity_log')->nullable()->after('result_summary');
        });
    }

    public function down(): void
    {
        Schema::table('technical_audit_jobs', function (Blueprint $table) {
            $table->dropColumn('activity_log');
        });
    }
};
