<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
public function up(): void {
Schema::table('sites', function (Blueprint $table) {
$table->date('launch_date')->nullable()->after('status');
$table->date('transfer_date')->nullable()->after('launch_date');
});
}
public function down(): void {
Schema::table('sites', function (Blueprint $table) {
$table->dropColumn(['launch_date','transfer_date']);
});
}
};
