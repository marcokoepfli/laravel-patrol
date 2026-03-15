<?php

namespace MarcoKoepfli\LaravelPatrol;

use MarcoKoepfli\LaravelPatrol\Contracts\Rule;
use MarcoKoepfli\LaravelPatrol\Rules\AbstractRule;

class RuleRunner
{
    /**
     * @param  Rule[]  $rules
     */
    public function __construct(
        private readonly array $rules,
        private readonly ProjectContext $context,
    ) {}

    /**
     * Filter rules to those applicable for the current Laravel version.
     *
     * @return Rule[]
     */
    public function applicableRules(): array
    {
        $version = $this->context->version();

        return array_filter($this->rules, function (Rule $rule) use ($version) {
            $supported = $rule->supportedVersions();

            return empty($supported) || in_array($version, $supported);
        });
    }

    public function run(): ResultCollection
    {
        $collection = new ResultCollection;
        $version = $this->context->version();

        foreach ($this->applicableRules() as $rule) {
            if ($rule instanceof AbstractRule) {
                $rule->setVersion($version);
            }

            $results = $rule->check($this->context);
            $collection->merge($results);
        }

        return $collection;
    }
}
