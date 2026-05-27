<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->string('external_resource_id');
            $table->string('external_resource_label')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'integration_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_integrations');
    }
};
