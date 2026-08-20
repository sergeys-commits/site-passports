<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('brand_key')->nullable()->after('name');
            $table->string('site_salt')->nullable()->after('brand_key');
            $table->string('profile_id', 16)->nullable()->after('site_salt');
            $table->unsignedInteger('profile_revision')->nullable()->after('profile_id');
            $table->string('public_token', 64)->nullable()->after('profile_revision');
            $table->string('theme_slug', 100)->nullable()->after('public_token');
            $table->string('theme_git_ref', 190)->nullable()->after('theme_version');
            $table->json('last_build_meta')->nullable()->after('theme_git_ref');
            $table->string('lifecycle', 32)->nullable()->after('status'); // staging|production|archived
            $table->string('scenario', 32)->nullable()->after('lifecycle'); // stage_then_prod|prod_basic_auth
            $table->boolean('profile_pipeline_enabled')->default(false)->after('scenario');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn([
                'brand_key',
                'site_salt',
                'profile_id',
                'profile_revision',
                'public_token',
                'theme_slug',
                'theme_git_ref',
                'last_build_meta',
                'lifecycle',
                'scenario',
                'profile_pipeline_enabled',
            ]);
        });
    }
};
