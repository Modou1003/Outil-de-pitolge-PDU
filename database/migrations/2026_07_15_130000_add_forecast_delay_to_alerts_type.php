<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $withNew = [
        'delay',
        'budget_overrun',
        'indicator_off_target',
        'milestone_missed',
        'no_update',
        'progress_gap',
        'physical_financial_gap',
        'forecast_delay',
    ];

    private array $previous = [
        'delay',
        'budget_overrun',
        'indicator_off_target',
        'milestone_missed',
        'no_update',
        'progress_gap',
        'physical_financial_gap',
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $values = "'" . implode("','", $this->withNew) . "'";
            DB::statement("ALTER TABLE alerts MODIFY COLUMN type ENUM($values) NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('alerts')->where('type', 'forecast_delay')->delete();
            $values = "'" . implode("','", $this->previous) . "'";
            DB::statement("ALTER TABLE alerts MODIFY COLUMN type ENUM($values) NOT NULL");
        }
    }
};
