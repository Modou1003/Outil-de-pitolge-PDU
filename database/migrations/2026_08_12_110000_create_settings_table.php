<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paramètres de l'application, à commencer par les seuils de déclenchement
 * des alertes et les bornes de coloration des indicateurs.
 *
 * Ces valeurs étaient jusqu'ici des constantes PHP : les ajuster imposait une
 * modification du code et un redéploiement. Elles deviennent modifiables par
 * l'administrateur, le code conservant ses valeurs par défaut en secours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
