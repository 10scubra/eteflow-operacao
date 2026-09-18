<?php

namespace App\Services;

use App\Models\ReadingSectionDefinition;
use App\Models\ReadingTemplate;
use App\Models\ReadingTemplateVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReadingTemplateService
{
    public function createVersion(ReadingTemplate $template, User $user, ?string $notes = null): ReadingTemplateVersion
    {
        return DB::transaction(function () use ($template, $user, $notes): ReadingTemplateVersion {
            $locked = ReadingTemplate::query()->lockForUpdate()->findOrFail($template->id);
            $next = ((int) $locked->versions()->max('version')) + 1;

            return $locked->versions()->create(['version' => $next, 'status' => ReadingTemplateVersion::Draft, 'notes' => $notes, 'created_by' => $user->id]);
        }, 3);
    }

    public function duplicate(ReadingTemplateVersion $source, User $user): ReadingTemplateVersion
    {
        return DB::transaction(function () use ($source, $user): ReadingTemplateVersion {
            $source = ReadingTemplateVersion::query()->with('sections.parameterRules.points')->lockForUpdate()->findOrFail($source->id);
            $copy = $this->createVersion($source->template, $user, 'Duplicada da versão '.$source->version);

            foreach ($source->sections as $section) {
                $sectionCopy = $copy->sections()->create($section->only(['section_key', 'label', 'description', 'sort_order', 'version', 'is_active', 'effective_from', 'effective_until']));
                foreach ($section->parameterRules as $rule) {
                    $ruleCopy = $sectionCopy->parameterRules()->create($rule->only(['equipment_id', 'section_key', 'field_key', 'label', 'data_type', 'description', 'unit', 'decimal_places', 'minimum_value', 'maximum_value', 'sort_order', 'condition_operator', 'reference_value', 'options', 'frequency_type', 'frequency_config', 'operational_rule', 'visibility_config', 'is_required', 'is_active', 'version', 'effective_from', 'effective_until']));
                    foreach ($rule->points as $point) {
                        $ruleCopy->points()->create($point->only(['stable_key', 'label', 'sort_order', 'is_active', 'instruction']));
                    }
                }
            }

            return $copy->load('sections.parameterRules.points');
        }, 3);
    }

    public function publish(ReadingTemplateVersion $version, User $user): ReadingTemplateVersion
    {
        return DB::transaction(function () use ($version, $user): ReadingTemplateVersion {
            $locked = ReadingTemplateVersion::query()->with('sections.parameterRules')->lockForUpdate()->findOrFail($version->id);
            if (! $locked->isEditable()) {
                throw ValidationException::withMessages(['version' => 'Somente versões em rascunho podem ser publicadas.']);
            }
            if ($locked->sections->isEmpty() || $locked->sections->contains(fn (ReadingSectionDefinition $section) => $section->parameterRules->isEmpty())) {
                throw ValidationException::withMessages(['version' => 'Inclua ao menos uma seção com parâmetro antes de publicar.']);
            }

            ReadingTemplateVersion::query()->where('reading_template_id', $locked->reading_template_id)->where('status', ReadingTemplateVersion::Published)->update(['status' => ReadingTemplateVersion::Archived]);
            $locked->update(['status' => ReadingTemplateVersion::Published, 'published_by' => $user->id, 'published_at' => now()]);

            return $locked->fresh('sections.parameterRules.points');
        }, 3);
    }

    public function assertEditable(ReadingTemplateVersion $version): void
    {
        if (! $version->isEditable()) {
            throw ValidationException::withMessages(['version' => 'Versão publicada ou arquivada é imutável. Duplique-a para alterar.']);
        }
    }
}
