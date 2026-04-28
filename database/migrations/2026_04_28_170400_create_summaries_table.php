<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('length', 20);
            $table->text('content');
            $table->string('model', 80)->nullable();
            $table->unsignedInteger('token_cost')->default(0);
            $table->timestamps();

            $table->unique(['document_id', 'length']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('summaries');
    }
};
