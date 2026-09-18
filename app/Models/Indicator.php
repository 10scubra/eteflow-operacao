<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Indicator extends Model
{
    protected $fillable = ['operational_unit_id', 'dashboard_page_id', 'stable_key', 'name', 'description', 'source_type', 'parameter_rule_id', 'parameter_rule_point_id', 'laboratory_parameter_id', 'aggregation', 'visualization', 'unit', 'periodicity', 'visual_configuration', 'operational_configuration', 'sort_order', 'is_active', 'is_shareable'];

    protected function casts(): array
    {
        return ['visual_configuration' => 'array', 'operational_configuration' => 'array', 'sort_order' => 'integer', 'is_active' => 'boolean', 'is_shareable' => 'boolean'];
    }

    public function operationalUnit(): BelongsTo
    {
        return $this->belongsTo(OperationalUnit::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(DashboardPage::class, 'dashboard_page_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ParameterRule::class, 'parameter_rule_id');
    }

    public function point(): BelongsTo
    {
        return $this->belongsTo(ParameterRulePoint::class, 'parameter_rule_point_id');
    }

    public function laboratoryParameter(): BelongsTo
    {
        return $this->belongsTo(LaboratoryParameter::class);
    }
}
