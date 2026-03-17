<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
public function up(): void
{
Schema::create('deployment_run_guards', function (Blueprint $table) {
$table->id();
$table->string('scope_key', 64);
$table->string('active_scope_key', 64)->nullable();
$table->foreignId('deployment_run_id')->constrained('deployment_runs')->cascadeOnDelete();
$table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
$table->boolean('is_active')->default(true);
$table->timestamp('acquired_at')->nullable();
$table->timestamp('expires_at')->nullable();
$table->timestamp('released_at')->nullable();
$table->string('release_reason', 64)->nullable();
$table->timestamps();

$table->index(['scope_key', 'is_active', 'expires_at'], 'idx_guard_scope_active_expires');
$table->unique('active_scope_key', 'uq_guard_active_scope_key');
});
}

public function down(): void
{
Schema::dropIfExists('deployment_run_guards');
}
};
