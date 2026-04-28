<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->unsignedInteger('page_number')->nullable();
            $table->text('content');
            $table->unsignedInteger('token_count')->default(0);
            $table->timestamps();

            $table->index(['document_id', 'chunk_index']);
        });

        // pgvector: add embedding column + ivfflat index. Other drivers fall back to a JSON column.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE document_chunks ADD COLUMN embedding vector(1536)');
            // Index created later (after we have data) via php artisan insightdeck:reindex.
            // ivfflat requires data to train on; defer index creation to keep migrations idempotent.
        } else {
            Schema::table('document_chunks', function (Blueprint $table) {
                $table->json('embedding')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
