<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('host')->nullable();
            $table->unsignedSmallInteger('ssh_port')->default(22);
            $table->string('ssh_user')->nullable();
            $table->string('ssh_key_path')->nullable();
            $table->string('connection', 20)->default('local'); // local|ssh
            $table->string('panel_type', 20)->default('none'); // isp|hestia|none
            $table->string('wp_sites_root');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
