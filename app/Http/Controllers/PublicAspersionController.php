<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavePublicAspersionRequest;
use App\Models\Aspersion;
use App\Models\AspersionPoint;
use App\Services\DailyOperationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicAspersionController extends Controller
{
    public function show(string $token): View
    {
        $point = AspersionPoint::query()
            ->where('public_token', $token)
            ->where('is_active', true)
            ->firstOrFail();

        return view('aspersion.public', [
            'point' => $point,
            'activeAspersion' => $this->activeAspersion($point),
        ]);
    }

    public function store(
        SavePublicAspersionRequest $request,
        string $token,
        DailyOperationService $operations,
    ): RedirectResponse {
        $data = $request->validated();

        DB::transaction(function () use ($data, $token, $operations): void {
            $point = AspersionPoint::query()
                ->where('public_token', $token)
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();
            $activeAspersion = $this->activeAspersion($point, true);

            if ($activeAspersion && $data['action'] !== 'end') {
                throw ValidationException::withMessages([
                    'action' => 'Esta bomba já possui uma aspersão ativa. Atualize a página para finalizar.',
                ]);
            }

            if (! $activeAspersion && $data['action'] !== 'start') {
                throw ValidationException::withMessages([
                    'action' => 'Esta aspersão já foi finalizada. Atualize a página antes de registrar novamente.',
                ]);
            }

            if (! $activeAspersion) {
                $this->start($point, $operations, $data);

                return;
            }

            $this->finish($activeAspersion, $data);
        }, 3);

        $message = $data['action'] === 'start'
            ? 'Aspersão iniciada. O horário foi registrado automaticamente.'
            : 'Aspersão finalizada. Duração e consumo foram calculados.';

        return redirect()->route('aspersion.public.show', $token)->with('success', $message);
    }

    private function start(AspersionPoint $point, DailyOperationService $operations, array $data): void
    {
        if ((int) $data['active_cannons'] < 1 || (float) $data['flow_rate'] <= 0) {
            throw ValidationException::withMessages([
                'active_cannons' => 'Para iniciar, informe ao menos um canhão e uma vazão maior que zero.',
            ]);
        }

        $shift = $operations->ensure();

        Aspersion::query()->create([
            'shift_id' => $shift->id,
            'aspersion_point_id' => $point->id,
            'area' => $point->location ?: $point->name,
            'line' => $point->name,
            'status' => 'active',
            'source' => 'qr',
            'initial_reading' => $data['totalizer'],
            'initial_flow_rate' => $data['flow_rate'],
            'initial_active_cannons' => $data['active_cannons'],
            'notes' => $data['notes'] ?? null,
            'started_at' => now(),
        ]);
    }

    private function finish(Aspersion $aspersion, array $data): void
    {
        $finalReading = (float) $data['totalizer'];
        $initialReading = (float) $aspersion->initial_reading;

        if ($finalReading < $initialReading) {
            throw ValidationException::withMessages([
                'totalizer' => 'A leitura final não pode ser menor que a leitura inicial.',
            ]);
        }

        $aspersion->update([
            'status' => 'completed',
            'final_reading' => $finalReading,
            'final_flow_rate' => $data['flow_rate'],
            'final_active_cannons' => $data['active_cannons'],
            'total_consumption' => $finalReading - $initialReading,
            'notes' => $data['notes'] ?? $aspersion->notes,
            'ended_at' => now(),
        ]);
    }

    private function activeAspersion(AspersionPoint $point, bool $lock = false): ?Aspersion
    {
        $query = Aspersion::query()
            ->where('aspersion_point_id', $point->id)
            ->where('status', 'active')
            ->latest('started_at');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }
}
