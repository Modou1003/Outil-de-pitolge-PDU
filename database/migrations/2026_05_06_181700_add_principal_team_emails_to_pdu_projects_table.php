<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdu_projects', function (Blueprint $table) {
            $table->string('director_email')->nullable()->after('director_name');
            $table->string('project_manager_email')->nullable()->after('project_manager_name');
            $table->string('financial_agent_email')->nullable()->after('financial_agent_name');
        });
    }

    public function down(): void
    {
        Schema::table('pdu_projects', function (Blueprint $table) {
            $table->dropColumn([
                'director_email',
                'project_manager_email',
                'financial_agent_email',
            ]);
        });
    }
};
