<?php

namespace App\Services;

use App\Models\Indicator;
use App\Models\LaboratoryResult;
use App\Models\ReadingValue;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class IndicatorDataService
{
    public function data(Indicator $indicator, CarbonInterface $from, CarbonInterface $to, bool $public = false): array
    {
        $rows = match ($indicator->source_type) {
            'OPERATIONAL_READING' => $this->operational($indicator, $from, $to, $public),
            'LAB_ANALYSIS','EXTERNAL_ANALYSIS' => $this->laboratory($indicator, $from, $to, $public),
            default => collect(),
        };
        $values = $rows->pluck('value')->filter(fn ($v) => $v !== null)->values();
        $summary = $this->aggregate($values, $indicator->aggregation);

        return ['indicator' => $indicator->only(['stable_key', 'name', 'description', 'source_type', 'aggregation', 'visualization', 'unit']), 'series' => $rows->values()->all(), 'value' => $summary, 'last_data_at' => $rows->max('at'), 'state' => $rows->isEmpty() ? 'NO_DATA' : 'READY'];
    }

    private function operational(Indicator $indicator, CarbonInterface $from, CarbonInterface $to, bool $public): Collection
    {
        if (! $indicator->parameter_rule_id) {
            return collect();
        }

        return ReadingValue::query()->where('parameter_rule_id', $indicator->parameter_rule_id)->when($indicator->parameter_rule_point_id, fn ($q) => $q->where('parameter_rule_point_id', $indicator->parameter_rule_point_id))
            ->where('semantic_status', 'MEASURED')->whereNotNull('value_numeric')->whereBetween('created_at', [$from, $to])
            ->whereHas('section.round.shift', fn ($q) => $q->where('operational_unit_id', $indicator->operational_unit_id))
            ->with(['point', 'rule'])->orderBy('created_at')->get()->map(fn ($v) => ['at' => $v->created_at->toIso8601String(), 'label' => $v->point?->label ?? $v->rule?->label ?? $v->field_key, 'value' => (float) $v->value_numeric, 'unit' => $v->unit ?? $indicator->unit, 'minimum' => $v->minimum_at_time === null ? null : (float) $v->minimum_at_time, 'maximum' => $v->maximum_at_time === null ? null : (float) $v->maximum_at_time] + ($public ? [] : ['recorded_by' => $v->recorded_by]));
    }

    private function laboratory(Indicator $indicator, CarbonInterface $from, CarbonInterface $to, bool $public): Collection
    {
        if (! $indicator->laboratory_parameter_id) {
            return collect();
        }

        return LaboratoryResult::query()->where('laboratory_parameter_id', $indicator->laboratory_parameter_id)
            ->whereHas('collection', fn ($q) => $q->where('operational_unit_id', $indicator->operational_unit_id)->where('origin_type', $indicator->source_type === 'EXTERNAL_ANALYSIS' ? 'EXTERNAL' : 'INTERNAL')->whereBetween('collected_at', [$from, $to]))
            ->with(['collection.point', 'corrections'])->get()->sortBy('collection.collected_at')->map(fn ($r) => ['at' => $r->collection->collected_at->toIso8601String(), 'label' => $r->collection->point->name, 'value' => $r->effectiveValue(), 'unit' => $r->unit]);
    }

    private function aggregate(Collection $values, string $aggregation): ?float
    {
        if ($values->isEmpty()) {
            return null;
        }

        return match ($aggregation) {
            'LATEST' => (float) $values->last(),'AVERAGE' => (float) $values->average(),'MIN' => (float) $values->min(),'MAX' => (float) $values->max(),'SUM' => (float) $values->sum(),'COUNT' => (float) $values->count(),'DELTA' => (float) $values->last() - (float) $values->first(),'RAW' => null,default => null
        };
    }
}
