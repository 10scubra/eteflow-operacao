<?php

namespace App\Support;

final class ReadingDefinitionLabels
{
    public const DATA_TYPES = [
        'percentage' => 'Percentual',
        'decimal' => 'Número decimal',
        'integer' => 'Número inteiro',
        'totalizer' => 'Totalizador',
        'boolean' => 'Sim / Não',
        'single_select' => 'Seleção',
        'equipment_status' => 'Estado do equipamento',
        'time' => 'Horário',
        'text' => 'Texto curto',
        'textarea' => 'Texto longo',
    ];

    public const CONDITIONS = [
        'BETWEEN' => 'Entre',
        'GREATER_THAN' => 'Maior que',
        'GREATER_THAN_OR_EQUAL' => 'Maior ou igual a',
        'LESS_THAN' => 'Menor que',
        'LESS_THAN_OR_EQUAL' => 'Menor ou igual a',
        'EQUAL' => 'Igual a',
        'REFERENCE' => 'Valor de referência',
    ];

    public const FREQUENCIES = [
        'EVERY_ROUND' => 'Todas as rodadas',
        'ONCE_PER_SHIFT' => 'Uma vez por turno',
        'SHIFT_START' => 'Início do turno',
        'SHIFT_END' => 'Final do turno',
        'EVERY_N_HOURS' => 'A cada X horas',
        'SPECIFIC_TIMES' => 'Horários específicos',
        'SPECIFIC_DAYS' => 'Dias específicos',
    ];

    public const SEMANTIC_STATUSES = [
        'MEASURED' => 'Medido',
        'NO_FLOW' => 'Sem vazão',
        'METER_FAULT' => 'Medidor com defeito',
        'NOT_MEASURED' => 'Leitura não realizada',
        'EQUIPMENT_STOPPED' => 'Equipamento parado',
        'NOT_APPLICABLE' => 'Não aplicável',
    ];

    public static function all(): array
    {
        return [
            'data_types' => self::DATA_TYPES,
            'conditions' => self::CONDITIONS,
            'frequencies' => self::FREQUENCIES,
            'semantic_statuses' => self::SEMANTIC_STATUSES,
        ];
    }
}
