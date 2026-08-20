<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('servers', 'connection') || Schema::hasColumn('servers', 'access_type')) {
            return;
        }

        DB::statement('ALTER TABLE servers CHANGE `connection` `access_type` VARCHAR(20) NOT NULL DEFAULT \'local\'');
    }

    public function down(): void
    {
        if (! Schema::hasColumn('servers', 'access_type') || Schema::hasColumn('servers', 'connection')) {
            return;
        }

        DB::statement('ALTER TABLE servers CHANGE `access_type` `connection` VARCHAR(20) NOT NULL DEFAULT \'local\'');
    }
};
