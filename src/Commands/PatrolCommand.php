<?php

namespace MarcoKoepfli\LaravelPatrol\Commands;

use Illuminate\Console\Command;
use MarcoKoepfli\LaravelPatrol\Enums\Severity;
use MarcoKoepfli\LaravelPatrol\Patrol;
use MarcoKoepfli\LaravelPatrol\ResultCollection;

class PatrolCommand extends Command
{
    protected $signature = 'patrol
        {--rule= : Run a specific rule by ID}
        {--severity=warning : Minimum severity to report (error, warning, info)}
        {--format=text : Output format (text, json)}';

    protected $description = 'Check your Laravel application follows conventions and best practices';

    public function handle(Patrol $patrol): int
    {
        $format = $this->option('format');

        if ($format === 'json') {
            return $this->handleJson($patrol);
        }

        return $this->handleText($patrol);
    }

    private function handleText(Patrol $patrol): int
    {
        $version = $patrol->version();
        $rules = $patrol->applicableRules();

        $this->components->info("Laravel Patrol v1.0 | Laravel {$version->value} | {$this->countRules($rules)} rules");
        $this->newLine();

        $results = $patrol->run();
        $results = $this->filterBySeverity($results);

        if ($results->count() === 0) {
            $this->components->info('No violations found. Your app follows the Laravel way!');

            return self::SUCCESS;
        }

        $ruleFilter = $this->option('rule');
        $grouped = $results->groupByFile();

        foreach ($grouped as $file => $fileResults) {
            $this->components->twoColumnDetail("<fg=white;options=bold>{$file}</>", '');

            foreach ($fileResults as $result) {
                if ($ruleFilter && $result->ruleId !== $ruleFilter) {
                    continue;
                }

                $severity = $result->severity;
                $color = $severity->color();
                $label = $severity->label();

                $location = $result->line ? ":{$result->line}" : '';
                $this->line("  <fg={$color}>[{$label}]</> {$result->message}{$location}");

                if ($result->suggestion) {
                    $this->line("         <fg=gray>{$result->suggestion}</>");
                }

                if ($result->docsUrl) {
                    $this->line("         <fg=gray;href={$result->docsUrl}>Docs: {$result->docsUrl}</>");
                }
            }

            $this->newLine();
        }

        $this->renderSummary($results);

        return $results->hasErrors() ? self::FAILURE : self::SUCCESS;
    }

    private function handleJson(Patrol $patrol): int
    {
        $results = $patrol->run();
        $results = $this->filterBySeverity($results);

        $this->line(json_encode([
            'version' => $patrol->version()->value,
            'total' => $results->count(),
            'errors' => $results->bySeverity(Severity::Error)->count(),
            'warnings' => $results->bySeverity(Severity::Warning)->count(),
            'info' => $results->bySeverity(Severity::Info)->count(),
            'results' => $results->toArray(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $results->hasErrors() ? self::FAILURE : self::SUCCESS;
    }

    private function filterBySeverity(ResultCollection $results): ResultCollection
    {
        $minSeverity = $this->option('severity');
        $severities = match ($minSeverity) {
            'error' => [Severity::Error],
            'warning' => [Severity::Error, Severity::Warning],
            'info' => [Severity::Error, Severity::Warning, Severity::Info],
            default => [Severity::Error, Severity::Warning],
        };

        $filtered = new ResultCollection;
        foreach ($results as $result) {
            if (in_array($result->severity, $severities)) {
                $filtered->add($result);
            }
        }

        return $filtered;
    }

    private function renderSummary(ResultCollection $results): void
    {
        $errors = $results->bySeverity(Severity::Error)->count();
        $warnings = $results->bySeverity(Severity::Warning)->count();
        $info = $results->bySeverity(Severity::Info)->count();

        $parts = [];
        if ($errors > 0) {
            $parts[] = "<fg=red>{$errors} error(s)</>";
        }
        if ($warnings > 0) {
            $parts[] = "<fg=yellow>{$warnings} warning(s)</>";
        }
        if ($info > 0) {
            $parts[] = "<fg=blue>{$info} info</>";
        }

        $this->components->error("Found {$results->count()} violation(s): ".implode(', ', $parts));
    }

    private function countRules(array $rules): int
    {
        return count($rules);
    }
}
