<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
public function up(): void
{
Schema::table('deployment_runs', function (Blueprint $table) {
if (!Schema::hasColumn('deployment_runs', 'lock_key')) {
$table->string('lock_key', 64)->nullable()->after('meta_json');
}
});
}

public function down(): void
{
Schema::table('deployment_runs', function (Blueprint $table) {
if (Schema::hasColumn('deployment_runs', 'lock_key')) {
$table->dropColumn('lock_key');
}
});
}
};
