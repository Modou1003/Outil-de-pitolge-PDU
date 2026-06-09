<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdu_projects', function (Blueprint $table) {
            $table->string('director_name')->nullable()->after('director_id');
            $table->string('project_manager_name')->nullable()->after('project_manager_id');
            $table->string('financial_agent_name')->nullable()->after('financial_agent_id');
        });
    }

    public function down(): void
    {
        Schema::table('pdu_projects', function (Blueprint $table) {
            $table->dropColumn(['director_name', 'project_manager_name', 'financial_agent_name']);
        });
    }
};

