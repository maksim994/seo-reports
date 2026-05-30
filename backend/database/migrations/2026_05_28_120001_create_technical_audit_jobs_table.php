<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_audit_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('queued');
            $table->string('site_url');
            $table->string('site_name')->nullable();
            $table->json('sample_urls')->nullable();
            $table->string('crawl_depth', 16)->default('light');
            $table->string('lang', 8)->default('ru');
            $table->string('cursor_agent_id')->nullable();
            $table->string('cursor_run_id')->nullable();
            $table->string('webhook_token', 64)->unique();
            $table->json('result_summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['project_id', 'created_at']);
            $table->index('cursor_agent_id');
        });

        Schema::create('technical_audit_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technical_audit_job_id')->constrained()->cascadeOnDelete();
            $table->string('format', 16);
            $table->string('path');
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();

            $table->unique(['technical_audit_job_id', 'format']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_audit_files');
        Schema::dropIfExists('technical_audit_jobs');
    }
};
