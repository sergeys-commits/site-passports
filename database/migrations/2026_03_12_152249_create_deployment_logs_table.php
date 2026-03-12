<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
public function up(): void {
Schema::create('deployment_logs', function (Blueprint $table) {
$table->id();
$table->foreignId('run_id')->constrained('deployment_runs')->cascadeOnDelete();
$table->string('stream', 20)->default('system'); // stdout|stderr|system
$table->unsignedInteger('line_no')->default(0);
$table->longText('message');
$table->timestamps();

$table->index(['run_id','line_no']);
});
}

public function down(): void {
Schema::dropIfExists('deployment_logs');
}
};
