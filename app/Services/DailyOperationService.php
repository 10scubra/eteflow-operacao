<?php

namespace App\Services;

use App\Models\ReadingRound;
use App\Models\ReadingSection;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class DailyOperationService
{
    public const DAY_ROUND_TIMES = ['08:30', '10:30', '12:30', '14:30', '16:30', '18:30'];

    public const NIGHT_ROUND_TIMES = ['20:30', '22:30', '00:30', '02:30', '04:30', '06:30'];

    public const SECTIONS = [
        'process' => 'Processo',
        'flotators' => 'Flotadores',
        'decanter' => 'Decanter',
        'totalizers' => 'Totalizadores',
        'chemicals' => 'Produtos químicos',
        'observations' => 'Observações',
    ];

    public function ensure(?CarbonInterface $moment = null): Shift
    {
        $now = ($moment ? Carbon::instance($moment) : now())->copy()->timezone(config('app.timezone'));
        $isDay = $now->hour >= 8 && $now->hour < 20;

        // Entre 00:00 e 07:59 o turno noturno continua pertencendo à data
        // em que começou, evitando criar outro turno na virada do dia.
        $operationalDate = (! $isDay && $now->hour < 8)
            ? $now->copy()->subDay()
            : $now->copy();

        $start = $operationalDate->copy()->setTime($isDay ? 8 : 20, 0);
        $end = $start->copy()->addHours(12);
        $startTime = $isDay ? '08:00:00' : '20:00:00';
        $endTime = $isDay ? '20:00:00' : '08:00:00';
        $label = $isDay ? 'diurno' : 'noturno';

        $shift = Shift::query()->firstOrCreate(
            ['shift_date' => $operationalDate->toDateString(), 'starts_at' => $startTime],
            ['ends_at' => $endTime, 'status' => 'active', 'notes' => "Turno {$label} gerado automaticamente."],
        );

        Shift::query()
            ->whereKeyNot($shift->id)
            ->where('status', 'active')
            ->update(['status' => 'closed', 'closed_at' => $now]);

        if ($shift->status !== 'active') {
            $shift->update(['status' => 'active', 'closed_at' => null]);
        }

        if ($shift->wasRecentlyCreated) {
            $operators = User::query()
                ->where('role', 'operator')
                ->where('is_active', true)
                ->oldest('id')
                ->limit(2)
                ->get();

            foreach ($operators as $operator) {
                $shift->members()->attach($operator->id, ['joined_at' => $start]);
            }
        }

        $roundTimes = $isDay ? self::DAY_ROUND_TIMES : self::NIGHT_ROUND_TIMES;

        foreach ($roundTimes as $index => $time) {
            // A soma a partir do início mantém 00:30–06:30 no dia seguinte.
            $scheduledAt = $start->copy()->addMinutes(30)->addHours($index * 2);
            $round = ReadingRound::query()->firstOrCreate(
                ['shift_id' => $shift->id, 'scheduled_at' => $scheduledAt],
                ['status' => 'pending'],
            );

            foreach (self::SECTIONS as $key => $sectionLabel) {
                ReadingSection::query()->firstOrCreate(
                    ['reading_round_id' => $round->id, 'section_key' => $key],
                    ['label' => $sectionLabel, 'status' => 'pending'],
                );
            }
        }

        return $shift->fresh(['members', 'rounds.sections']);
    }

    public function timeline(Shift $shift, ?CarbonInterface $moment = null): array
    {
        $now = ($moment ? Carbon::instance($moment) : now())->copy()->timezone(config('app.timezone'));
        $rounds = $shift->rounds()->with(['sections.lastEditedBy'])->orderBy('scheduled_at')->get();
        $current = $rounds->where('scheduled_at', '<=', $now)->last();
        $next = $rounds->where('scheduled_at', '>', $now)->first();

        return [
            'now' => $now,
            'rounds' => $rounds,
            'current' => $current,
            'next' => $next,
            'display' => $current ?? $next ?? $rounds->last(),
        ];
    }

    public function type(Shift $shift): string
    {
        return str_starts_with($shift->starts_at, '20:') ? 'night' : 'day';
    }

    public function label(Shift $shift): string
    {
        return $this->type($shift) === 'night' ? 'Noturno' : 'Diurno';
    }
}
