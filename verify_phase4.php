<?php

require_once __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== VÉRIFICATION RAPIDE - PHASE 1.04 ===\n\n";

$checks = [
    'Universities' => ['model' => 'App\Models\University', 'expected' => 8],
    'PDU Projects' => ['model' => 'App\Models\PduProject', 'expected' => '16-24'],
    'Indicators' => ['model' => 'App\Models\Indicator', 'expected' => 11],
    'Indicator Trackings' => ['model' => 'App\Models\IndicatorTracking', 'expected' => '200+'],
    'Reports' => ['model' => 'App\Models\Report', 'expected' => '50+'],
    'Documents' => ['model' => 'App\Models\Document', 'expected' => '100+'],
    'Comments' => ['model' => 'App\Models\Comment', 'expected' => '100+'],
    'Notifications' => ['model' => 'App\Models\Notification', 'expected' => '50+'],
    'Audit Logs' => ['model' => 'App\Models\AuditLog', 'expected' => '100+'],
    'Users' => ['model' => 'App\Models\User', 'expected' => '10+'],
];

$allGood = true;

foreach ($checks as $name => $check) {
    try {
        $count = $check['model']::count();
        $expected = $check['expected'];

        if (is_string($expected) && str_contains($expected, '+')) {
            $min = (int) str_replace('+', '', $expected);
            $status = $count >= $min ? '✓' : '✗';
        } elseif (is_string($expected) && str_contains($expected, '-')) {
            [$min, $max] = explode('-', $expected);
            $status = ($count >= (int)$min && $count <= (int)$max) ? '✓' : '✗';
        } else {
            $status = $count == (int)$expected ? '✓' : '✗';
        }

        echo sprintf("%-20s: %s %d (attendu: %s)\n", $name, $status, $count, $expected);

        if ($status === '✗') {
            $allGood = false;
        }
    } catch (Exception $e) {
        echo sprintf("%-20s: ✗ ERREUR - %s\n", $name, $e->getMessage());
        $allGood = false;
    }
}

echo "\n";

if ($allGood) {
    echo "🎉 PHASE 1.04 - SUCCÈS COMPLET !\n";
    echo "Base de données complètement initialisée avec données réalistes.\n";
    echo "Prêt pour les phases suivantes (CRUD, tableaux de bord, etc.).\n";
} else {
    echo "⚠️  PHASE 1.04 - PROBLÈMES DÉTECTÉS\n";
    echo "Vérifiez les migrations et seeders avant de continuer.\n";
}

echo "\n=== UTILISATEURS DE TEST ===\n";
echo "Admin: admin@pdu-tracker.local / password\n";
echo "Directeur: directeur@pdu-tracker.local / password\n";
echo "Chef Projet: chef@pdu-tracker.local / password\n";
echo "Financier: financier@pdu-tracker.local / password\n";
echo "Visiteur: visiteur@pdu-tracker.local / password\n";