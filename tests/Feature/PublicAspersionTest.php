<?php

namespace Tests\Feature;

use App\Models\Aspersion;
use App\Models\AspersionPoint;
use App\Models\User;
use App\Services\DailyOperationService;
use Database\Seeders\EteFlowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAspersionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EteFlowSeeder::class);
    }

    public function test_public_qr_page_starts_and_finishes_aspersion_without_login(): void
    {
        $point = AspersionPoint::query()->create([
            'name' => 'Bomba QR teste',
            'location' => 'Painel inferior',
            'public_token' => 'token-publico-seguro-para-teste-001',
            'is_active' => true,
        ]);

        $this->get(route('aspersion.public.show', $point->public_token))
            ->assertOk()
            ->assertSee('Iniciar aspersão')
            ->assertDontSee('Dashboard Master');

        $this->post(route('aspersion.public.store', $point->public_token), [
            'action' => 'start',
            'totalizer' => 1500,
            'flow_rate' => 22.5,
            'active_cannons' => 4,
        ])->assertRedirect(route('aspersion.public.show', $point->public_token));

        $aspersion = Aspersion::query()->where('aspersion_point_id', $point->id)->firstOrFail();
        $this->assertSame('active', $aspersion->status);
        $this->assertSame('qr', $aspersion->source);
        $this->assertNull($aspersion->started_by);

        $this->post(route('aspersion.public.store', $point->public_token), [
            'action' => 'end',
            'totalizer' => 1512.75,
            'flow_rate' => 21.8,
            'active_cannons' => 0,
        ])->assertRedirect(route('aspersion.public.show', $point->public_token));

        $aspersion->refresh();
        $this->assertSame('completed', $aspersion->status);
        $this->assertSame('12.750', $aspersion->total_consumption);
        $this->assertSame('21.800', $aspersion->final_flow_rate);
        $this->assertSame(0, $aspersion->final_active_cannons);
        $this->assertNotNull($aspersion->ended_at);
    }

    public function test_public_qr_page_rejects_stale_action_and_invalid_totalizer(): void
    {
        $point = AspersionPoint::query()->create([
            'name' => 'Bomba concorrente',
            'public_token' => 'token-publico-seguro-para-teste-002',
            'is_active' => true,
        ]);

        $payload = ['action' => 'start', 'totalizer' => 200, 'flow_rate' => 18, 'active_cannons' => 2];
        $this->post(route('aspersion.public.store', $point->public_token), $payload)->assertSessionHasNoErrors();
        $this->post(route('aspersion.public.store', $point->public_token), $payload)->assertSessionHasErrors('action');

        $this->post(route('aspersion.public.store', $point->public_token), [
            'action' => 'end',
            'totalizer' => 199,
            'flow_rate' => 0,
            'active_cannons' => 0,
        ])->assertSessionHasErrors('totalizer');

        $this->assertSame('active', Aspersion::query()->where('aspersion_point_id', $point->id)->firstOrFail()->status);
    }

    public function test_invalid_or_inactive_qr_code_returns_404(): void
    {
        $this->get(route('aspersion.public.show', 'nao-existe'))->assertNotFound();

        $point = AspersionPoint::query()->create([
            'name' => 'Bomba inativa',
            'public_token' => 'token-publico-seguro-para-teste-003',
            'is_active' => false,
        ]);

        $this->get(route('aspersion.public.show', $point->public_token))->assertNotFound();
    }

    public function test_master_creates_point_and_opens_printable_qr_plate(): void
    {
        $master = User::query()->where('username', 'master.teste')->firstOrFail();

        $this->actingAs($master)->post(route('aspersion-points.store'), [
            'name' => 'Bomba de aspersão 02',
            'location' => 'Painel elétrico sul',
        ])->assertSessionHas('success');

        $point = AspersionPoint::query()->where('name', 'Bomba de aspersão 02')->firstOrFail();
        $this->assertSame(48, strlen($point->public_token));

        $this->actingAs($master)->get(route('aspersion-points.plate', $point))
            ->assertOk()
            ->assertSee('Imprimir plaquinha')
            ->assertSee('data:image/svg+xml;base64', false);
    }

    public function test_operator_cannot_create_point_or_open_qr_plate(): void
    {
        $operator = User::query()->where('username', 'operador1')->firstOrFail();
        $point = AspersionPoint::query()->firstOrFail();

        $this->actingAs($operator)->post(route('aspersion-points.store'), [
            'name' => 'Ponto não autorizado',
        ])->assertForbidden();

        $this->actingAs($operator)->get(route('aspersion-points.plate', $point))->assertForbidden();
        $this->assertDatabaseMissing('aspersion_points', ['name' => 'Ponto não autorizado']);
    }

    public function test_master_history_shows_day_and_night_totals_and_filters(): void
    {
        $master = User::query()->where('username', 'master.teste')->firstOrFail();
        $point = AspersionPoint::query()->firstOrFail();
        $aspersion = Aspersion::query()->where('aspersion_point_id', $point->id)->firstOrFail();
        $dayShift = app(DailyOperationService::class)->ensure(now()->startOfDay()->setHour(10));
        $aspersion->update([
            'shift_id' => $dayShift->id,
            'status' => 'completed',
            'final_reading' => 1260,
            'total_consumption' => 10,
            'final_flow_rate' => 17.5,
            'final_active_cannons' => 0,
            'ended_at' => now(),
        ]);

        $this->actingAs($master)->get(route('aspersion', ['shift_type' => 'day']))
            ->assertOk()
            ->assertSee('Controle total da aspersão')
            ->assertSee('Bomba de aspersão 01')
            ->assertSee('10,000 m³');
    }
}
