<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('admin_url')->nullable()->after('stage_domain');
            $table->string('stage_admin_url')->nullable()->after('admin_url');
            $table->string('wp_admin_password')->nullable()->after('stage_admin_url');
        });
    }

    public function down(): void {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['admin_url', 'stage_admin_url', 'wp_admin_password']);
        });
    }
};
