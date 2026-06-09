<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('physical_progresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdu_project_id')->constrained('pdu_projects')->onDelete('cascade');
            $table->foreignId('project_lot_id')->nullable()->constrained('project_lots')->onDelete('cascade');
            $table->string('period', 7); // YYYY-MM
            $table->date('measurement_date');
            $table->decimal('planned_percentage', 5, 2)->default(0);
            $table->decimal('actual_percentage', 5, 2)->default(0);
            $table->text('observations')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('status', ['draft', 'submitted', 'validated'])->default('submitted');
            $table->timestamps();

            $table->index(['pdu_project_id', 'period']);
            $table->index(['project_lot_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('physical_progresses');
    }
};
