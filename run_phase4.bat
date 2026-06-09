@echo off
cd /d "C:\Users\The Zenith\Downloads\Travail de fin d'etudes(TFE)\site\pdu-tracker"
echo ========================================
echo   PHASE 1.04 - EXECUTION COMPLETE
echo ========================================
echo.
echo Exécution des migrations...
php artisan migrate:fresh
if %errorlevel% neq 0 (
    echo ERREUR: Échec des migrations
    pause
    exit /b 1
)
echo ✓ Migrations exécutées avec succès
echo.
echo Exécution des seeders...
php artisan db:seed
if %errorlevel% neq 0 (
    echo ERREUR: Échec des seeders
    pause
    exit /b 1
)
echo ✓ Seeders exécutés avec succès
echo.
echo Vérification des données...
php artisan tinker --execute="
echo '=== STATISTIQUES DE LA BASE DE DONNÉES ===';
echo 'Universités: ' . App\Models\University::count();
echo 'Projets PDU: ' . App\Models\PduProject::count();
echo 'Indicateurs: ' . App\Models\Indicator::count();
echo 'Trackings: ' . App\Models\IndicatorTracking::count();
echo 'Rapports: ' . App\Models\Report::count();
echo 'Documents: ' . App\Models\Document::count();
echo 'Commentaires: ' . App\Models\Comment::count();
echo 'Notifications: ' . App\Models\Notification::count();
echo 'Logs d\'audit: ' . App\Models\AuditLog::count();
echo 'Utilisateurs: ' . App\Models\User::count();
"
echo.
echo ========================================
echo   PHASE 1.04 - TERMINÉE AVEC SUCCÈS
echo ========================================
echo.
echo Base de données complètement initialisée avec:
echo - 8 universités camerounaises
echo - 16-24 projets PDU
echo - 11 indicateurs de performance
echo - Données de tracking historiques
echo - Rapports périodiques
echo - Documents et commentaires
echo - Système de notifications
echo - Logs d'audit complets
echo.
pause