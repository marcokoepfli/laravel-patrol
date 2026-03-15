<?php

namespace MarcoKoepfli\LaravelPatrol;

use MarcoKoepfli\LaravelPatrol\Contracts\Rule;
use MarcoKoepfli\LaravelPatrol\Enums\LaravelVersion;
use MarcoKoepfli\LaravelPatrol\Presets\RecommendedPreset;
use MarcoKoepfli\LaravelPatrol\Presets\RelaxedPreset;
use MarcoKoepfli\LaravelPatrol\Presets\StrictPreset;

class Patrol
{
    /** @var Rule[] */
    private array $rules = [];

    private ProjectContext $context;

    public function __construct(
        private readonly LaravelVersion $version,
        private readonly string $basePath,
        private readonly array $config = [],
    ) {
        $this->context = new ProjectContext(
            version: $this->version,
            basePath: $this->basePath,
            paths: $this->config['paths'] ?? ['app', 'routes', 'config', 'resources'],
            exclude: $this->config['exclude'] ?? ['vendor', 'node_modules', 'storage'],
        );

        $this->resolveRules();
    }

    public function run(): ResultCollection
    {
        $runner = new RuleRunner($this->rules, $this->context);

        return $runner->run();
    }

    public function version(): LaravelVersion
    {
        return $this->version;
    }

    /**
     * @return Rule[]
     */
    public function rules(): array
    {
        return $this->rules;
    }

    /**
     * @return Rule[]
     */
    public function applicableRules(): array
    {
        $runner = new RuleRunner($this->rules, $this->context);

        return $runner->applicableRules();
    }

    private function resolveRules(): void
    {
        $presetName = $this->config['preset'] ?? 'recommended';
        $preset = match ($presetName) {
            'strict' => new StrictPreset,
            'relaxed' => new RelaxedPreset,
            default => new RecommendedPreset,
        };

        $ruleClasses = $preset->rules();

        // Merge custom rules
        $customRules = $this->config['custom_rules'] ?? [];
        $ruleClasses = array_merge($ruleClasses, $customRules);

        // Apply rule overrides from config
        $overrides = $this->config['rules'] ?? [];
        foreach ($overrides as $ruleClass => $enabled) {
            if ($enabled && ! in_array($ruleClass, $ruleClasses)) {
                $ruleClasses[] = $ruleClass;
            } elseif (! $enabled) {
                $ruleClasses = array_filter($ruleClasses, fn ($class) => $class !== $ruleClass);
            }
        }

        $this->rules = array_map(fn ($class) => new $class, array_values($ruleClasses));
    }
}
