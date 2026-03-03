<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->longText('content');
            $table->integer('version')->default(1);
            $table->string('artifact_type');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_artifacts');
    }
};
