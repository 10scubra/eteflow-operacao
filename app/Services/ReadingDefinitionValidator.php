<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class ReadingDefinitionValidator
{
    public const DataTypes = ['integer', 'decimal', 'percentage', 'totalizer', 'boolean', 'single_select', 'equipment_status', 'time', 'text', 'textarea'];

    public const FrequencyTypes = ['EVERY_ROUND', 'ONCE_PER_SHIFT', 'SHIFT_START', 'SHIFT_END', 'EVERY_N_HOURS', 'SPECIFIC_TIMES', 'SPECIFIC_DAYS'];

    public const ConditionOperators = ['BETWEEN', 'GREATER_THAN', 'GREATER_THAN_OR_EQUAL', 'LESS_THAN', 'LESS_THAN_OR_EQUAL', 'EQUAL', 'REFERENCE'];

    public const SemanticStatuses = ['MEASURED', 'NO_FLOW', 'METER_FAULT', 'NOT_MEASURED', 'EQUIPMENT_STOPPED', 'NOT_APPLICABLE'];

    public const EquipmentStates = ['OPERATING', 'STOPPED', 'MAINTENANCE', 'FAILURE', 'UNAVAILABLE'];

    public function validateFrequency(string $type, ?array $config): array
    {
        $config ??= [];
        $valid = match ($type) {
            'EVERY_N_HOURS' => isset($config['hours']) && is_int($config['hours']) && $config['hours'] >= 1 && $config['hours'] <= 24,
            'SPECIFIC_TIMES' => isset($config['times']) && is_array($config['times']) && $config['times'] !== [] && collect($config['times'])->every(fn ($time) => is_string($time) && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)),
            'SPECIFIC_DAYS' => isset($config['days']) && is_array($config['days']) && $config['days'] !== [] && collect($config['days'])->every(fn ($day) => is_int($day) && $day >= 1 && $day <= 7),
            default => $config === [],
        };

        if (! $valid) {
            throw ValidationException::withMessages(['frequency_config' => 'Configuração de frequência inválida para o tipo selecionado.']);
        }

        return $config;
    }

    public function isOutOfCondition(mixed $value, string $operator, mixed $minimum, mixed $maximum, mixed $reference): bool
    {
        if (! is_numeric($value)) {
            return false;
        }
        $number = (float) $value;

        return match ($operator) {
            'BETWEEN' => ($minimum !== null && $number < (float) $minimum) || ($maximum !== null && $number > (float) $maximum),
            'GREATER_THAN' => $minimum !== null && ! ($number > (float) $minimum),
            'GREATER_THAN_OR_EQUAL' => $minimum !== null && ! ($number >= (float) $minimum),
            'LESS_THAN' => $maximum !== null && ! ($number < (float) $maximum),
            'LESS_THAN_OR_EQUAL' => $maximum !== null && ! ($number <= (float) $maximum),
            'EQUAL' => $reference !== null && $number !== (float) $reference,
            'REFERENCE' => false,
            default => false,
        };
    }
}
