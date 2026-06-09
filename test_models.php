<?php

require_once __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing PDU Models...\n\n";

try {
    // Test 1: Vérifier que les modèles existent
    echo "1. Testing model classes exist...\n";
    $models = [
        'App\Models\University',
        'App\Models\PduProject',
        'App\Models\Indicator',
        'App\Models\IndicatorTracking',
        'App\Models\Document',
        'App\Models\Comment',
        'App\Models\Notification',
        'App\Models\AuditLog',
        'App\Models\Report',
    ];

    foreach ($models as $model) {
        if (class_exists($model)) {
            echo "   ✓ $model exists\n";
        } else {
            echo "   ✗ $model NOT FOUND\n";
        }
    }

    // Test 2: Vérifier que les tables existent
    echo "\n2. Testing database tables exist...\n";
    $tables = [
        'universities',
        'pdu_projects',
        'indicators',
        'indicator_trackings',
        'documents',
        'comments',
        'notifications',
        'audit_logs',
        'reports',
    ];

    foreach ($tables as $table) {
        try {
            $count = \Illuminate\Support\Facades\DB::table($table)->count();
            echo "   ✓ $table exists ($count records)\n";
        } catch (Exception $e) {
            echo "   ✗ $table NOT FOUND\n";
        }
    }

    // Test 3: Tester la création d'instances
    echo "\n3. Testing model instantiation...\n";

    // Créer une université
    $university = new \App\Models\University([
        'name' => 'Test University',
        'acronym' => 'TU',
        'location' => 'Test City',
        'status' => 'active',
    ]);

    if ($university->save()) {
        echo "   ✓ University created successfully\n";

        // Créer un projet
        $project = new \App\Models\PduProject([
            'title' => 'Test PDU Project',
            'description' => 'Test project description',
            'university_id' => $university->id,
            'status' => 'draft',
            'budget_allocated' => 1000000,
            'currency' => 'XAF',
        ]);

        if ($project->save()) {
            echo "   ✓ PDU Project created successfully\n";

            // Créer un indicateur
            $indicator = new \App\Models\Indicator([
                'name' => 'Test Indicator',
                'code' => 'TI',
                'category' => 'academic',
                'type' => 'percentage',
                'target_value' => 85,
                'is_active' => true,
            ]);

            if ($indicator->save()) {
                echo "   ✓ Indicator created successfully\n";
            } else {
                echo "   ✗ Failed to create indicator\n";
            }
        } else {
            echo "   ✗ Failed to create project\n";
        }
    } else {
        echo "   ✗ Failed to create university\n";
    }

    echo "\n✓ All tests completed!\n";

} catch (Exception $e) {
    echo "Erreur lors des tests: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}