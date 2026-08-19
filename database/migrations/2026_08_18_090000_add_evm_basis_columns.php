<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rendre explicite la base de la méthode de la valeur acquise.
 *
 * L'enveloppe contractuelle de chaque ouvrage était jusqu'ici déduite à
 * l'import puis oubliée, de sorte que le budget à l'achèvement du périmètre
 * suivi n'était plus connu : le coût final estimé se calculait sur le marché
 * entier, alors que les indices de performance portaient sur les seuls
 * ouvrages. La conserver permet de diviser la bonne grandeur.
 *
 * Le drapeau porté sur les décomptes distingue par ailleurs une avance d'un
 * décompte de travaux, faute de quoi une avance enregistrée comme décompte
 * était comptée deux fois dans l'encaissement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('building_works', function (Blueprint $table) {
            if (! Schema::hasColumn('building_works', 'contract_amount')) {
                $table->decimal('contract_amount', 15, 2)->nullable()->after('weight_percentage');
            }
        });

        Schema::table('project_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('project_payments', 'is_advance')) {
                $table->boolean('is_advance')->default(false)->after('gross_amount');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('building_works', 'contract_amount')) {
            Schema::table('building_works', fn (Blueprint $table) => $table->dropColumn('contract_amount'));
        }

        if (Schema::hasColumn('project_payments', 'is_advance')) {
            Schema::table('project_payments', fn (Blueprint $table) => $table->dropColumn('is_advance'));
        }
    }
};
