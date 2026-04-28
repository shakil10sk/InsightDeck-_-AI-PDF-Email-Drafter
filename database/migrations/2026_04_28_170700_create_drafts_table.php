<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_draft_id')->nullable()->constrained('drafts')->nullOnDelete();
            $table->text('goal');
            $table->string('recipient')->nullable();
            $table->string('tone', 20)->default('friendly');
            $table->string('length', 20)->default('medium');
            $table->text('context')->nullable();
            $table->text('output');
            $table->string('provider', 20)->default('openai');
            $table->string('model', 80)->default('gpt-4o-mini');
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('parent_draft_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drafts');
    }
};
