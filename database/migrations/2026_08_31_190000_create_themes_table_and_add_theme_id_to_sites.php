<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('git_repo');
            $table->string('src_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->foreignId('theme_id')
                ->nullable()
                ->after('theme_slug')
                ->constrained('themes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropConstrainedForeignId('theme_id');
        });

        Schema::dropIfExists('themes');
    }
};
