<?php

require_once __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    $tables = \Illuminate\Support\Facades\DB::select("SELECT name FROM sqlite_master WHERE type='table'");
    echo "Tables existantes dans la base de données:\n";
    foreach ($tables as $table) {
        echo "- " . $table->name . "\n";
    }

    // Vérifier spécifiquement nos tables PDU
    $pduTables = ['universities', 'pdu_projects', 'indicators', 'indicator_trackings', 'documents', 'comments', 'notifications', 'audit_logs', 'reports'];
    echo "\nTables PDU:\n";
    foreach ($pduTables as $table) {
        $exists = \Illuminate\Support\Facades\DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
        echo "- $table: " . (count($exists) > 0 ? "EXISTS" : "NOT FOUND") . "\n";
    }

} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}