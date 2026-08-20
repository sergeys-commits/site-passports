<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_artifacts', function (Blueprint $table) {
            $table->id();
            $table->string('cache_key')->unique();
            $table->string('git_ref');
            $table->string('git_sha', 64);
            $table->string('profile_id', 16);
            $table->unsignedInteger('profile_revision');
            $table->string('salt_fingerprint', 32);
            $table->string('storage_path');
            $table->json('build_meta')->nullable();
            $table->timestamp('built_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_artifacts');
    }
};
