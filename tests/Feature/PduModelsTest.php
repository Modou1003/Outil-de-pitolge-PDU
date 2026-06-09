<?php

namespace Tests\Feature;

use App\Models\University;
use App\Models\PduProject;
use App\Models\Indicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PduModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_university_model_can_be_created()
    {
        $university = University::factory()->create([
            'name' => 'Université de Yaoundé I',
            'acronym' => 'UY1',
            'location' => 'Yaoundé',
        ]);

        $this->assertDatabaseHas('universities', [
            'name' => 'Université de Yaoundé I',
            'acronym' => 'UY1',
        ]);
    }

    public function test_pdu_project_model_can_be_created()
    {
        $university = University::factory()->create();

        $project = PduProject::factory()->create([
            'title' => 'Projet PDU Test',
            'university_id' => $university->id,
        ]);

        $this->assertDatabaseHas('pdu_projects', [
            'title' => 'Projet PDU Test',
            'university_id' => $university->id,
        ]);
    }

    public function test_indicator_model_can_be_created()
    {
        $indicator = Indicator::factory()->create([
            'name' => 'Taux de réussite académique',
            'code' => 'TA',
            'category' => 'academic',
            'type' => 'percentage',
        ]);

        $this->assertDatabaseHas('indicators', [
            'name' => 'Taux de réussite académique',
            'code' => 'TA',
        ]);
    }

    public function test_models_relationships_work()
    {
        $university = University::factory()->create();
        $project = PduProject::factory()->create(['university_id' => $university->id]);
        $indicator = Indicator::factory()->create();

        // Test relationships
        $this->assertInstanceOf(University::class, $project->university);
        $this->assertEquals($university->id, $project->university->id);

        $this->assertInstanceOf(PduProject::class, $project);
        $this->assertEquals($project->id, $project->id);
    }
}