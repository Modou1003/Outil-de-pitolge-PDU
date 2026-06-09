<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_progresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdu_project_id')->constrained('pdu_projects')->onDelete('cascade');
            $table->string('period', 7); // YYYY-MM
            $table->date('measurement_date');
            // EVM metrics
            $table->decimal('planned_value', 15, 2)->default(0);    // PV (BCWS)
            $table->decimal('earned_value', 15, 2)->default(0);     // EV (BCWP)
            $table->decimal('actual_cost', 15, 2)->default(0);      // AC (ACWP)
            $table->decimal('cumulative_planned_value', 15, 2)->default(0);
            $table->decimal('cumulative_earned_value', 15, 2)->default(0);
            $table->decimal('cumulative_actual_cost', 15, 2)->default(0);
            $table->text('observations')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('status', ['draft', 'submitted', 'validated'])->default('submitted');
            $table->timestamps();

            $table->index(['pdu_project_id', 'period']);
            $table->unique(['pdu_project_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_progresses');
    }
};
