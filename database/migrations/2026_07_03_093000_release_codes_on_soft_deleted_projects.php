<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('pdu_projects')
            ->select('id', 'code')
            ->whereNotNull('deleted_at')
            ->orderBy('id')
            ->chunkById(200, function ($projects): void {
                foreach ($projects as $project) {
                    if (str_contains($project->code, '__deleted__')) {
                        continue;
                    }

                    $suffix = '__deleted__' . $project->id;
                    $newCode = Str::limit((string) $project->code, 255 - strlen($suffix), '') . $suffix;

                    DB::table('pdu_projects')
                        ->where('id', $project->id)
                        ->update(['code' => $newCode]);
                }
            });
    }

    public function down(): void
    {
        // No-op: we keep adjusted historical codes.
    }
};
