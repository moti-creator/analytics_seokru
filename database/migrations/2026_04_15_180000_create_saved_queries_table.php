<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_queries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained()->cascadeOnDelete();
            $table->string('label', 120);
            $table->text('prompt');
            $table->timestamps();
            $table->index('connection_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_queries');
    }
};
