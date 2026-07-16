<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('building_works', function (Blueprint $table) {
            // Pondération de l'ouvrage dans l'avancement physique du projet.
            $table->decimal('weight_percentage', 5, 2)->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('building_works', function (Blueprint $table) {
            $table->dropColumn('weight_percentage');
        });
    }
};
