<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('site_plugins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id');
$table->index('site_id');

            $table->string('plugin_slug');
            $table->string('plugin_version', 50)->nullable();
            $table->boolean('active')->default(false);
            $table->timestamps();
            $table->unique(['site_id','plugin_slug']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('site_plugins');
    }
};
