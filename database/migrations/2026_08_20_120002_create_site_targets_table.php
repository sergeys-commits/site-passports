<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('kind', 20); // staging|production
            $table->string('domain');
            $table->foreignId('server_id')->constrained('servers')->restrictOnDelete();
            $table->string('docroot')->nullable();
            $table->string('wp_path')->nullable();
            $table->boolean('basic_auth')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('wp_config_pins_written')->default(false);
            $table->timestamps();

            $table->unique(['site_id', 'kind', 'domain']);
            $table->index(['server_id', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_targets');
    }
};
