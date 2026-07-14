<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_milestones', function (Blueprint $table) {
            $table->foreignId('building_work_id')->nullable()->constrained('building_works')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_milestones', function (Blueprint $table) {
            $table->dropForeignKeyIfExists(['building_work_id']);
            $table->dropColumn('building_work_id');
        });
    }
};
