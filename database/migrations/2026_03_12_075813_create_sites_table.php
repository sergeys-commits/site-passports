<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('domain')->unique();
            $table->string('stage_domain')->nullable();
            $table->foreignId('group_id')->nullable()->constrained('site_groups')->nullOnDelete();
            $table->string('theme_name')->nullable();
            $table->string('theme_version', 50)->nullable();
            $table->timestamp('theme_changed_at')->nullable();
            $table->string('php_version', 50)->nullable();
            $table->string('wp_version', 50)->nullable();
            $table->enum('status', ['active','stage','archived'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('sites');
    }
};
