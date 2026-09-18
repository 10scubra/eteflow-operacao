<?php

namespace App\Services;

use App\Models\ParameterRule;
use App\Models\ReadingRound;
use App\Models\ReadingSectionDefinition;
use App\Models\ReadingTemplateVersion;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReadingFrequencyService
{
    public function applies(ParameterRule $rule, ReadingRound $round): bool
    {
        $round->loadMissing('shift.template.rounds');
        $frequency = $rule->frequency_type ?? 'EVERY_ROUND';
        $scheduled = $round->scheduled_at->copy()->timezone(config('app.timezone'));
        $offset = $this->offsetMinutes($round->shift, $scheduled);
        $generalOffsets = $this->generalOffsets($round->shift);

        return match ($frequency) {
            'SPECIFIC_TIMES' => in_array($scheduled->format('H:i'), $rule->frequency_config['times'] ?? [], true),
            'ONCE_PER_SHIFT', 'SHIFT_START' => $offset === ($generalOffsets->first() ?? 0),
            'SHIFT_END' => $offset === $generalOffsets->last(),
            'EVERY_N_HOURS' => isset($rule->frequency_config['hours'])
                && $offset >= 0
                && $offset % ((int) $rule->frequency_config['hours'] * 60) === 0,
            'SPECIFIC_DAYS' => in_array($round->shift->shift_date->dayOfWeekIso, $rule->frequency_config['days'] ?? [], true),
            default => $generalOffsets->contains($offset),
        };
    }

    public function sectionApplies(ReadingSectionDefinition $section, ReadingRound $round): bool
    {
        $section->loadMissing('parameterRules');
        $activeRules = $section->parameterRules->where('is_active', true);

        return $activeRules->isEmpty()
            || $activeRules->contains(fn (ParameterRule $rule): bool => $this->applies($rule, $round));
    }

    /** @return Collection<int, Carbon> */
    public function supplementalMoments(ReadingTemplateVersion $version, Shift $shift): Collection
    {
        $times = $version->sections()
            ->with('parameterRules')
            ->get()
            ->flatMap(fn (ReadingSectionDefinition $section) => $section->parameterRules
                ->where('is_active', true)
                ->where('frequency_type', 'SPECIFIC_TIMES')
                ->flatMap(fn (ParameterRule $rule) => $rule->frequency_config['times'] ?? []))
            ->unique();
        [$start, $end] = $this->window($shift);

        return $times->map(function (string $time) use ($start, $end): ?Carbon {
            $moment = $start->copy()->setTimeFromTimeString($time);
            if ($moment->lt($start)) {
                $moment->addDay();
            }

            return $moment->gte($start) && $moment->lt($end) ? $moment : null;
        })->filter()->sort()->values();
    }

    private function generalOffsets(Shift $shift): Collection
    {
        $configured = $shift->template?->rounds?->where('is_active', true)->sortBy('sort_order')->pluck('offset_minutes');

        return ($configured && $configured->isNotEmpty() ? $configured : collect([30, 150, 270, 390, 510, 630]))
            ->map(fn ($offset): int => (int) $offset)
            ->values();
    }

    private function offsetMinutes(Shift $shift, Carbon $scheduled): int
    {
        [$start] = $this->window($shift);

        return (int) $start->diffInMinutes($scheduled, false);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function window(Shift $shift): array
    {
        $start = $shift->shift_date->copy()->setTimeFromTimeString($shift->starts_at);
        $end = $shift->shift_date->copy()->setTimeFromTimeString($shift->ends_at);
        if ($end->lte($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }
}
