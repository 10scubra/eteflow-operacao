<?php

namespace App\Services;

use App\Models\ReadingRound;
use App\Models\ReadingSection;
use App\Models\ReadingSectionDefinition;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class DailyOperationService
{
    public function __construct(private OperationalConfigurationService $configuration, private ReadingFrequencyService $frequencies) {}

    public function ensure(?CarbonInterface $moment = null): Shift
    {
        $now = ($moment ? Carbon::instance($moment) : now())->copy()->timezone(config('app.timezone'));
        $configuration = $this->configuration->shiftAt($now);
        $operationalDate = $configuration['operational_date'];
        $start = $configuration['starts_at'];
        $end = $configuration['ends_at'];
        $startTime = $start->format('H:i:s');
        $endTime = $end->format('H:i:s');
        $label = $configuration['template']?->name ?? ($startTime === '20:00:00' ? 'noturno' : 'diurno');

        $shift = Shift::query()->firstOrCreate(
            ['shift_date' => $operationalDate, 'starts_at' => $startTime],
            ['shift_template_id' => $configuration['template']?->id, 'ends_at' => $endTime, 'status' => 'active', 'notes' => "Turno {$label} gerado automaticamente."],
        );
        $readingTemplateVersion = $this->configuration->publishedReadingTemplate();

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

        $scheduledMoments = collect($configuration['round_offsets'])
            ->map(fn (int $offsetMinutes) => $start->copy()->addMinutes($offsetMinutes));
        if ($readingTemplateVersion) {
            $scheduledMoments = $scheduledMoments->merge($this->frequencies->supplementalMoments($readingTemplateVersion, $shift));
        }

        foreach ($scheduledMoments->unique(fn ($moment) => $moment->toDateTimeString())->sort() as $scheduledAt) {
            $round = ReadingRound::query()->firstOrCreate(
                ['shift_id' => $shift->id, 'scheduled_at' => $scheduledAt],
                ['reading_template_version_id' => $readingTemplateVersion?->id, 'status' => 'pending'],
            );

            foreach ($this->configuration->sectionsAt($scheduledAt, $round->templateVersion ?? $readingTemplateVersion) as $section) {
                if ($section['id'] && ! $this->frequencies->sectionApplies(ReadingSectionDefinition::findOrFail($section['id']), $round)) {
                    continue;
                }
                ReadingSection::query()->firstOrCreate(
                    ['reading_round_id' => $round->id, 'section_key' => $section['key']],
                    [
                        'reading_section_definition_id' => $section['id'],
                        'label' => $section['label'],
                        'status' => 'pending',
                    ],
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
