@echo off
cd /d "C:\Users\The Zenith\Downloads\Travail de fin d'etudes(TFE)\site\pdu-tracker"
echo Checking migration status...
php artisan migrate:status
echo.
echo Checking database tables...
php artisan tinker --execute="echo 'Tables: '; DB::select(\"SELECT name FROM sqlite_master WHERE type='table'\");"
pause