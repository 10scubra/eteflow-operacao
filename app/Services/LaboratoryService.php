<?php

namespace App\Services;

use App\Models\LaboratoryCollection;
use App\Models\LaboratoryParameter;
use App\Models\LaboratoryResult;
use App\Models\LaboratoryResultCorrection;
use App\Models\OperationalUnit;
use App\Models\SamplingPoint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LaboratoryService
{
    public function __construct(private AuditService $audit) {}

    public function createCollection(OperationalUnit $unit, User $user, array $data): LaboratoryCollection
    {
        return DB::transaction(function () use ($unit, $user, $data) {
            $point = SamplingPoint::where('operational_unit_id', $unit->id)->where('is_active', true)->findOrFail($data['sampling_point_id']);
            $parameters = LaboratoryParameter::where('operational_unit_id', $unit->id)->where('is_active', true)->whereIn('id', array_keys(array_filter($data['results'], fn ($v) => $v !== null && $v !== '')))->get()->keyBy('id');
            if ($parameters->isEmpty()) {
                throw ValidationException::withMessages(['results' => 'Informe ao menos um resultado válido.']);
            }
            $collection = LaboratoryCollection::create(['operational_unit_id' => $unit->id, 'sampling_point_id' => $point->id, 'origin_type' => $data['origin_type'], 'collected_at' => $data['collected_at'], 'result_received_at' => $data['result_received_at'] ?? null, 'external_laboratory' => $data['external_laboratory'] ?? null, 'document_reference' => $data['document_reference'] ?? null, 'observation' => $data['observation'] ?? null, 'created_by' => $user->id]);
            foreach ($parameters as $parameter) {
                $raw = $data['results'][$parameter->id];
                if (! is_numeric(str_replace(',', '.', (string) $raw))) {
                    throw ValidationException::withMessages(["results.{$parameter->id}" => 'Resultado inválido.']);
                }
                $value = round((float) str_replace(',', '.', (string) $raw), $parameter->decimal_places);
                $collection->results()->create(['laboratory_parameter_id' => $parameter->id, 'result_value' => $value, 'unit' => $parameter->default_unit, 'parameter_snapshot' => $parameter->only(['stable_key', 'name', 'default_unit', 'decimal_places', 'configuration']), 'created_by' => $user->id]);
            }
            $this->audit->record($collection, 'laboratory.collection_created', $user, [], ['unit_id' => $unit->id, 'point_id' => $point->id, 'origin_type' => $collection->origin_type, 'collected_at' => $collection->collected_at->toISOString(), 'results' => $parameters->count()]);

            return $collection->fresh(['point', 'results.parameter', 'creator']);
        }, 3);
    }

    public function correct(LaboratoryResult $result, float $value, string $reason, User $user): LaboratoryResultCorrection
    {
        return DB::transaction(function () use ($result, $value, $reason, $user) {
            $result = LaboratoryResult::lockForUpdate()->findOrFail($result->id);
            $original = $result->effectiveValue();
            $correction = $result->corrections()->create(['original_value' => $original, 'corrected_value' => $value, 'reason' => $reason, 'corrected_by' => $user->id, 'corrected_at' => now()]);
            $this->audit->record($correction, 'laboratory.result_corrected', $user, ['value' => $original], ['value' => $value, 'reason' => $reason]);

            return $correction;
        }, 3);
    }
}
