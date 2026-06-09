<?php

namespace Tests\Unit\Models;

use App\Models\University;
use App\Models\PduProject;
use App\Models\Indicator;
use App\Models\IndicatorTracking;
use App\Models\Document;
use App\Models\Comment;
use App\Models\Report;
use App\Models\Notification;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UniversityModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_university_has_fillable_attributes()
    {
        $fillable = [
            'name', 'acronym', 'location', 'address', 'phone', 'email',
            'website', 'description', 'status', 'metadata'
        ];

        $university = new University();

        foreach ($fillable as $attribute) {
            $this->assertContains($attribute, $university->getFillable());
        }
    }

    public function test_university_has_correct_casts()
    {
        $university = new University();
        $casts = $university->getCasts();

        $this->assertEquals('array', $casts['metadata']);
    }

    public function test_university_has_pdu_projects_relationship()
    {
        $university = University::factory()->create();
        $project = PduProject::factory()->create(['university_id' => $university->id]);

        $this->assertInstanceOf(PduProject::class, $university->pduProjects()->first());
        $this->assertEquals($project->id, $university->pduProjects()->first()->id);
    }

    public function test_university_has_reports_relationship()
    {
        $university = University::factory()->create();
        $report = Report::factory()->create(['university_id' => $university->id]);

        $this->assertInstanceOf(Report::class, $university->reports()->first());
        $this->assertEquals($report->id, $university->reports()->first()->id);
    }

    public function test_university_has_display_name_attribute()
    {
        $university = University::factory()->create([
            'name' => 'Université de Yaoundé I',
            'acronym' => 'UY1'
        ]);

        $this->assertEquals('Université de Yaoundé I (UY1)', $university->display_name);
    }

    public function test_university_scope_active()
    {
        University::factory()->create(['status' => 'active']);
        University::factory()->create(['status' => 'inactive']);

        $activeUniversities = University::active()->get();

        $this->assertEquals(1, $activeUniversities->count());
        $this->assertEquals('active', $activeUniversities->first()->status);
    }
}