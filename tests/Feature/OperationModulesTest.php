<?php

namespace Tests\Feature;

use App\Models\Aspersion;
use App\Models\Occurrence;
use App\Models\OperationalAction;
use App\Models\ShiftHandover;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\EteFlowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OperationModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-09 10:00:00');
        $this->seed(EteFlowSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_operator_can_open_every_restored_module(): void
    {
        $operator = User::where('username', 'operador1')->firstOrFail();
        foreach (['/operacao', '/leituras', '/acoes', '/aspersao', '/ocorrencias', '/passagem-turno', '/mais'] as $uri) {
            $this->actingAs($operator)->get($uri)->assertOk();
        }

        $this->actingAs($operator)->get('/operacao')
            ->assertDontSee('Situação atual do turno');
        $this->assertDatabaseCount('equipment', 7);
    }

    public function test_action_requires_checklist_and_photos_then_preserves_evidence(): void
    {
        Storage::fake('local');
        $operator = User::where('username', 'operador1')->firstOrFail();
        $action = OperationalAction::firstOrFail();
        $this->actingAs($operator)->post(route('actions.start', $action))->assertSessionHas('success');

        $this->actingAs($operator)->post(route('actions.complete', $action), [
            'observation' => 'Limpeza executada.',
            'checklist' => $action->checklistItems()->pluck('id')->all(),
        ])->assertSessionHasErrors('before_photo');

        $this->actingAs($operator)->post(route('actions.complete', $action), [
            'observation' => 'Limpeza executada e tubulação conferida.',
            'measured_value' => 18.4,
            'checklist' => $action->checklistItems()->pluck('id')->all(),
            'before_photo' => $this->image('antes.png'),
            'after_photo' => $this->image('depois.png'),
        ])->assertSessionHas('success');

        $this->assertSame('completed', $action->fresh()->status);
        $this->assertSame($operator->id, $action->fresh()->completed_by);
        $this->assertCount(2, $action->fresh()->evidences);
    }

    public function test_occurrence_can_be_registered_and_converted_to_action_by_master(): void
    {
        $operator = User::where('username', 'operador2')->firstOrFail();
        $master = User::where('username', 'master.teste')->firstOrFail();

        $this->actingAs($operator)->post(route('occurrences.create'), [
            'title' => 'Vazamento na bomba de recirculação',
            'location' => 'Casa de bombas',
            'priority' => 'high',
            'description' => 'Vedação em observação.',
        ])->assertSessionHas('success');

        $occurrence = Occurrence::where('title', 'Vazamento na bomba de recirculação')->firstOrFail();
        $this->actingAs($master)->post(route('occurrences.convert', $occurrence))->assertSessionHas('success');

        $this->assertNotNull($occurrence->fresh()->operational_action_id);
        $this->assertDatabaseHas('operational_actions', ['id' => $occurrence->fresh()->operational_action_id]);
    }

    public function test_aspersion_start_and_finish_calculates_consumption(): void
    {
        $operator = User::where('username', 'operador2')->firstOrFail();
        $this->actingAs($operator)->post(route('aspersion.start'), [
            'area' => 'Setor Sul',
            'line' => 'Linha 01',
            'initial_reading' => 200,
        ])->assertSessionHas('success');

        $aspersion = Aspersion::where('area', 'Setor Sul')->firstOrFail();
        $this->actingAs($operator)->post(route('aspersion.end', $aspersion), [
            'final_reading' => 212.5,
        ])->assertSessionHas('success');

        $this->assertSame('completed', $aspersion->fresh()->status);
        $this->assertSame('12.500', $aspersion->fresh()->total_consumption);
    }

    public function test_handover_saves_a_snapshot_and_author(): void
    {
        $operator = User::where('username', 'operador1')->firstOrFail();
        $this->actingAs($operator)->post(route('handover.close'), [
            'confirmed' => '1',
            'note' => 'Verificar o Aerador 03 no próximo turno.',
        ])->assertSessionHas('success');

        $handover = ShiftHandover::firstOrFail();
        $this->assertTrue($handover->confirmed);
        $this->assertSame($operator->id, $handover->confirmed_by);
        $this->assertArrayHasKey('pending_actions', $handover->summary);
    }

    public function test_application_exposes_basic_pwa_structure(): void
    {
        $operator = User::where('username', 'operador1')->firstOrFail();

        $this->actingAs($operator)
            ->get(route('operation.home'))
            ->assertOk()
            ->assertSee('manifest.webmanifest');

        $this->assertFileExists(public_path('manifest.webmanifest'));
        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('offline.html'));
        $this->assertFileExists(public_path('assets/icon.svg'));

        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/operacao', $manifest['start_url']);
    }

    private function image(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'eteflow-image-');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9WlS4AAAAASUVORK5CYII='));

        return new UploadedFile($path, $name, 'image/png', null, true);
    }
}
