<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_progresses', function (Blueprint $table) {
            $table->foreignId('project_lot_id')
                ->nullable()
                ->after('pdu_project_id')
                ->constrained('project_lots')
                ->nullOnDelete();

            $table->dropUnique(['pdu_project_id', 'period']);
            $table->unique(['pdu_project_id', 'project_lot_id', 'period'], 'financial_progress_project_lot_period_unique');
            $table->index(['pdu_project_id', 'project_lot_id', 'period'], 'financial_progress_project_lot_period_index');
        });
    }

    public function down(): void
    {
        Schema::table('financial_progresses', function (Blueprint $table) {
            $table->dropIndex('financial_progress_project_lot_period_index');
            $table->dropUnique('financial_progress_project_lot_period_unique');
            $table->unique(['pdu_project_id', 'period']);
            $table->dropConstrainedForeignId('project_lot_id');
        });
    }
};
