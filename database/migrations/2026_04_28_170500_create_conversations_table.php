<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->default('New conversation');
            $table->string('provider', 20)->default('openai');
            $table->string('model', 80)->default('gpt-4o-mini');
            $table->text('system_prompt')->nullable();
            $table->timestamp('pinned_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('conversation_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['conversation_id', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_documents');
        Schema::dropIfExists('conversations');
    }
};
