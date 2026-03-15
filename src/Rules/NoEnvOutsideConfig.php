<?php

namespace MarcoKoepfli\LaravelPatrol\Rules;

use MarcoKoepfli\LaravelPatrol\Enums\LaravelVersion;
use MarcoKoepfli\LaravelPatrol\ProjectContext;

class NoEnvOutsideConfig extends AbstractRule
{
    public function id(): string
    {
        return 'no-env-outside-config';
    }

    public function description(): string
    {
        return 'The env() function should only be used in config files. Use config() everywhere else.';
    }

    public function docsUrl(LaravelVersion $version): string
    {
        return $version->docsUrl('configuration#accessing-configuration-values');
    }

    public function check(ProjectContext $context): array
    {
        $violations = [];
        $analyzer = $context->analyzer();

        foreach ($context->phpFiles() as $file) {
            $relativePath = $context->relativePath($file->getRealPath());

            // env() is expected in config files
            if (str_starts_with($relativePath, 'config/')) {
                continue;
            }

            $code = $file->getContents();
            $ast = $analyzer->parse($code);

            if ($ast === null) {
                continue;
            }

            $envCalls = $analyzer->findFunctionCalls($ast, 'env');

            foreach ($envCalls as $call) {
                if ($this->shouldIgnore($call, $analyzer, $code)) {
                    continue;
                }

                $violations[] = $this->violation(
                    message: 'env() called outside of config files',
                    file: $relativePath,
                    line: $call->getStartLine(),
                    suggestion: 'Move this env() call to a config file and use config() to retrieve the value.',
                );
            }
        }

        return $violations;
    }
}
