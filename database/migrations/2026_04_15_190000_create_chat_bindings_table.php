<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_bindings', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20);              // 'telegram' | 'whatsapp' (future)
            $table->string('chat_id', 64);               // telegram chat_id as string
            $table->foreignId('connection_id')->nullable()->constrained()->nullOnDelete();
            $table->string('auth_token', 64)->nullable(); // one-time token used in ?tg= OAuth kickoff
            $table->timestamp('auth_token_expires_at')->nullable();
            $table->timestamps();

            $table->unique(['platform', 'chat_id']);
            $table->index('auth_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_bindings');
    }
};
