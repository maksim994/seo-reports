<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_job_id')->constrained()->cascadeOnDelete();
            $table->string('format', 16);
            $table->string('path');
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();

            $table->unique(['report_job_id', 'format']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_files');
    }
};
