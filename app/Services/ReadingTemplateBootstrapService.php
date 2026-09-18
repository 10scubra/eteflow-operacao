<?php

namespace App\Services;

use App\Models\ReadingTemplate;
use App\Models\ReadingTemplateVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReadingTemplateBootstrapService
{
    public function __construct(private ReadingTemplateService $templates) {}

    /**
     * @param  array{stable_key:string,name:string,description:?string,bootstrap_key:string,sections:array<int,array<string,mixed>>}  $definition
     */
    public function install(array $definition, User $author): ReadingTemplateVersion
    {
        return DB::transaction(function () use ($definition, $author): ReadingTemplateVersion {
            $template = ReadingTemplate::query()->updateOrCreate(
                ['stable_key' => $definition['stable_key']],
                ['name' => $definition['name'], 'description' => $definition['description'], 'is_active' => true, 'is_default' => true],
            );

            $existing = $template->versions()->where('notes', $definition['bootstrap_key'])->first();
            if ($existing) {
                return $existing->load('sections.parameterRules.points');
            }

            $version = $this->templates->createVersion($template, $author, $definition['bootstrap_key']);
            foreach ($definition['sections'] as $sectionData) {
                $parameters = $sectionData['parameters'];
                unset($sectionData['parameters']);
                $section = $version->sections()->create([
                    ...$sectionData,
                    'version' => $version->version,
                    'is_active' => true,
                    'effective_from' => now(),
                ]);

                foreach ($parameters as $parameterData) {
                    $points = $parameterData['points'] ?? [];
                    unset($parameterData['points']);
                    $rule = $section->parameterRules()->create([
                        ...$parameterData,
                        'section_key' => $section->section_key,
                        'version' => $version->version,
                        'is_active' => true,
                        'effective_from' => now(),
                    ]);
                    foreach ($points as $point) {
                        $rule->points()->create([...$point, 'is_active' => true]);
                    }
                }
            }

            return $this->templates->publish($version, $author);
        }, 3);
    }
}
