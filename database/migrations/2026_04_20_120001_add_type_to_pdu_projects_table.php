<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdu_projects', function (Blueprint $table) {
            $table->enum('type', [
                'construction',
                'rehabilitation',
                'equipement',
                'formation',
                'recherche',
                'numerique',
            ])->default('construction')->after('status');

            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('pdu_projects', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
