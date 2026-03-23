<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_groups', function (Blueprint $table) {
            $table->string('theme_name', 100)->nullable()->default('wp-theme-core')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('site_groups', function (Blueprint $table) {
            $table->dropColumn('theme_name');
        });
    }
};
