<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('plan_tier', 20)->default('free')->after('password');
            $table->string('default_provider', 20)->default('openai')->after('plan_tier');
            $table->string('default_model', 80)->default('gpt-4o-mini')->after('default_provider');
            $table->text('byo_openai_key_encrypted')->nullable()->after('default_model');
            $table->text('byo_anthropic_key_encrypted')->nullable()->after('byo_openai_key_encrypted');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'plan_tier',
                'default_provider',
                'default_model',
                'byo_openai_key_encrypted',
                'byo_anthropic_key_encrypted',
            ]);
        });
    }
};
