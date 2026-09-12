<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ParameterRule;
use App\Models\ReadingSection;
use App\Models\ReadingValue;
use App\Models\ReadingValueRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ReadingSectionService
{
    public function save(ReadingSection $section, User $user, array $payload): ReadingSection
    {
        return DB::transaction(function () use ($section, $user, $payload) {
            $locked = ReadingSection::query()
                ->with('round.shift')
                ->lockForUpdate()
                ->findOrFail($section->id);

            if (! $locked->round->shift->members()->whereKey($user->id)->exists()) {
                throw ValidationException::withMessages(['user' => 'O operador não pertence a este turno.']);
            }

            if ((int) $payload['lock_version'] !== $locked->lock_version) {
                throw new ConflictHttpException('Este bloco foi alterado por outro operador. Atualize os dados antes de salvar.');
            }

            $beforeSection = $locked->only(['status', 'editing_by', 'editing_started_at', 'started_by', 'completed_by', 'lock_version']);

            foreach ($payload['values'] as $entry) {
                $rule = ParameterRule::query()
                    ->where('section_key', $locked->section_key)
                    ->where('field_key', $entry['field_key'])
                    ->where('is_active', true)
                    ->orderByDesc('version')
                    ->first();

                $numeric = array_key_exists('value_numeric', $entry) ? $entry['value_numeric'] : null;
                $outOfRange = $numeric !== null && $rule && (
                    ($rule->minimum_value !== null && $numeric < $rule->minimum_value) ||
                    ($rule->maximum_value !== null && $numeric > $rule->maximum_value)
                );

                $value = ReadingValue::query()->firstOrNew([
                    'reading_section_id' => $locked->id,
                    'field_key' => $entry['field_key'],
                ]);
                $previous = $value->exists ? $value->only(['value_text', 'value_numeric', 'unit', 'is_out_of_range']) : null;

                $value->fill([
                    'parameter_rule_id' => $rule?->id,
                    'value_text' => $entry['value_text'] ?? null,
                    'value_numeric' => $numeric,
                    'unit' => $entry['unit'] ?? $rule?->unit,
                    'minimum_at_time' => $rule?->minimum_value,
                    'maximum_at_time' => $rule?->maximum_value,
                    'is_out_of_range' => $outOfRange,
                    'recorded_by' => $user->id,
                ])->save();

                $current = $value->only(['value_text', 'value_numeric', 'unit', 'is_out_of_range']);
                if ($previous !== $current) {
                    ReadingValueRevision::create([
                        'reading_value_id' => $value->id,
                        'previous_value' => $previous,
                        'new_value' => $current,
                        'changed_by' => $user->id,
                        'changed_at' => now(),
                        'reason' => $payload['reason'] ?? null,
                    ]);
                }
            }

            if ($locked->started_at === null) {
                $locked->started_at = now();
                $locked->started_by = $user->id;
            }

            $locked->status = $payload['status'];
            $locked->last_edited_by = $user->id;
            $locked->lock_version++;

            if ($payload['status'] === 'completed') {
                $locked->completed_at = now();
                $locked->completed_by = $user->id;
                $locked->editing_by = null;
                $locked->editing_started_at = null;
            } else {
                $locked->editing_by = $user->id;
                $locked->editing_started_at ??= now();
            }

            $locked->save();

            $sections = $locked->round->sections()->get();
            $roundStatus = $sections->every(fn ($item) => $item->status === 'completed')
                ? 'completed'
                : ($sections->contains(fn ($item) => in_array($item->status, ['in_progress', 'completed', 'reopened'], true)) ? 'in_progress' : 'pending');
            $locked->round->update(['status' => $roundStatus]);

            AuditLog::create([
                'auditable_type' => ReadingSection::class,
                'auditable_id' => $locked->id,
                'action' => 'reading_section.saved',
                'old_values' => $beforeSection,
                'new_values' => $locked->only(['status', 'editing_by', 'editing_started_at', 'started_by', 'completed_by', 'lock_version']),
                'user_id' => $user->id,
                'created_at' => now(),
            ]);

            return $locked->load(['values.recordedBy', 'startedBy', 'completedBy', 'lastEditedBy']);
        }, 3);
    }
}
