<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
Schema::table('sites', function (Blueprint $table) {
if (!Schema::hasColumn('sites', 'launch_date')) $table->date('launch_date')->nullable();
if (!Schema::hasColumn('sites', 'transfer_date')) $table->date('transfer_date')->nullable();
});
}

public function down(): void
{
Schema::table('sites', function (Blueprint $table) {
if (Schema::hasColumn('sites', 'launch_date')) $table->dropColumn('launch_date');
if (Schema::hasColumn('sites', 'transfer_date')) $table->dropColumn('transfer_date');
});
}
};
