<?php

namespace Tests\Feature;

use App\Models\Shift;
use App\Models\User;
use App\Services\DailyOperationService;
use App\Services\OperationSnapshotService;
use Carbon\Carbon;
use Database\Seeders\EteFlowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicOperationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_day_shift_has_general_and_totalizer_rounds_with_correct_timeline(): void
    {
        Carbon::setTestNow('2026-09-09 08:00:00');
        $this->seed(EteFlowSeeder::class);
        $service = app(DailyOperationService::class);
        $shift = $service->ensure();

        $this->assertSame('08:00:00', $shift->starts_at);
        $this->assertSame('20:00:00', $shift->ends_at);

        $before = $service->timeline($shift);
        $this->assertNull($before['current']);
        $this->assertSame('08:30', $before['next']->scheduled_at->format('H:i'));
        $this->assertSame(
            ['08:30', '09:00', '10:30', '12:00', '12:30', '14:30', '16:30', '18:30'],
            $before['rounds']->map->scheduled_at->map->format('H:i')->all(),
        );

        Carbon::setTestNow('2026-09-09 15:00:00');
        $during = $service->timeline($shift);
        $this->assertSame('14:30', $during['current']->scheduled_at->format('H:i'));
        $this->assertSame('16:30', $during['next']->scheduled_at->format('H:i'));

        Carbon::setTestNow('2026-09-09 19:59:59');
        $sameShift = $service->ensure();
        $this->assertSame($shift->id, $sameShift->id);
    }

    public function test_night_shift_starts_at_20_and_crosses_midnight(): void
    {
        $service = app(DailyOperationService::class);

        Carbon::setTestNow('2026-09-09 20:00:00');
        $night = $service->ensure();

        $this->assertSame('2026-09-09', $night->shift_date->format('Y-m-d'));
        $this->assertSame('20:00:00', $night->starts_at);
        $this->assertSame('08:00:00', $night->ends_at);
        $this->assertSame(
            ['20:30', '22:30', '00:30', '02:30', '04:30', '06:30'],
            $night->rounds()->orderBy('scheduled_at')->get()->map->scheduled_at->map->format('H:i')->all(),
        );

        Carbon::setTestNow('2026-09-10 00:10:00');
        $afterMidnight = $service->ensure();
        $timeline = $service->timeline($afterMidnight);

        $this->assertSame($night->id, $afterMidnight->id);
        $this->assertSame('22:30', $timeline['current']->scheduled_at->format('H:i'));
        $this->assertSame('00:30', $timeline['next']->scheduled_at->format('H:i'));

        Carbon::setTestNow('2026-09-10 07:59:59');
        $this->assertSame($night->id, $service->ensure()->id);

        Carbon::setTestNow('2026-09-10 08:00:00');
        $day = $service->ensure();
        $this->assertNotSame($night->id, $day->id);
        $this->assertSame('08:00:00', $day->starts_at);
    }

    public function test_new_day_creates_a_new_day_shift_without_reusing_rounds(): void
    {
        Carbon::setTestNow('2026-09-09 09:00:00');
        $this->seed(EteFlowSeeder::class);
        $service = app(DailyOperationService::class);
        $first = $service->ensure();

        Carbon::setTestNow('2026-09-10 09:00:00');
        $second = $service->ensure();

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, Shift::count());
        $this->assertSame(8, $first->rounds()->count());
        $this->assertSame(8, $second->rounds()->count());
    }

    public function test_snapshot_uses_server_time_current_round_and_shift_name(): void
    {
        Carbon::setTestNow('2026-09-09 15:12:00');
        $this->seed(EteFlowSeeder::class);

        $snapshot = app(OperationSnapshotService::class)->build();
        $current = collect($snapshot['rounds'])->firstWhere('id', $snapshot['current_round_id']);

        $this->assertSame('America/Sao_Paulo', $snapshot['timezone']);
        $this->assertStringStartsWith('2026-09-09T15:12:00', $snapshot['server_time']);
        $this->assertSame('day', $snapshot['shift']['type']);
        $this->assertSame('Diurno', $snapshot['shift']['name']);
        $this->assertSame('14:30', $current['time']);
        $this->assertSame(2, count($snapshot['shift']['members']));

        Carbon::setTestNow('2026-09-09 22:10:00');
        $nightSnapshot = app(OperationSnapshotService::class)->build();
        $this->assertSame('night', $nightSnapshot['shift']['type']);
        $this->assertSame('Noturno', $nightSnapshot['shift']['name']);
    }

    public function test_login_and_role_access_are_separated(): void
    {
        Carbon::setTestNow('2026-09-09 10:00:00');
        $this->seed(EteFlowSeeder::class);

        $this->post('/login', ['username' => 'operador1', 'password' => 'Operador1@2026'])
            ->assertRedirect(route('operation.home'));

        $operator = User::where('username', 'operador1')->firstOrFail();
        $master = User::where('username', 'master.teste')->firstOrFail();

        $this->actingAs($operator)->get('/master')->assertForbidden();
        $this->actingAs($master)->get('/master')->assertOk();
        $this->actingAs($operator)->get('/leituras')->assertOk();
    }
}
