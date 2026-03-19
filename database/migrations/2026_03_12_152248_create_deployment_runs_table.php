<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
public function up(): void {
Schema::create('deployment_runs', function (Blueprint $table) {
$table->id();
$table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
$table->string('action_type', 50); // stage_provision | promote_stage_to_prod
$table->string('mode', 20); // dry_run | live
$table->string('status', 20)->default('queued'); // queued|running|success|failed
$table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
$table->string('confirm_phrase_used')->nullable();
$table->timestamp('started_at')->nullable();
$table->timestamp('finished_at')->nullable();
$table->json('meta_json')->nullable();
$table->timestamps();

$table->index(['action_type','status']);
});
}

public function down(): void {
Schema::dropIfExists('deployment_runs');
}
};
