<?php

namespace MarcoKoepfli\LaravelPatrol;

use MarcoKoepfli\LaravelPatrol\Analyzers\FileAnalyzer;
use MarcoKoepfli\LaravelPatrol\Enums\LaravelVersion;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class ProjectContext
{
    /** @var array<string, string> */
    private array $fileCache = [];

    private readonly string $resolvedBasePath;

    private readonly FileAnalyzer $analyzer;

    public function __construct(
        private readonly LaravelVersion $version,
        private readonly string $basePath,
        private readonly array $paths,
        private readonly array $exclude,
    ) {
        $this->resolvedBasePath = realpath($this->basePath) ?: $this->basePath;
        $this->analyzer = new FileAnalyzer;
    }

    public function version(): LaravelVersion
    {
        return $this->version;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function analyzer(): FileAnalyzer
    {
        return $this->analyzer;
    }

    /**
     * Get all PHP files matching the configured paths.
     */
    public function phpFiles(): Finder
    {
        $finder = new Finder;
        $finder->files()->name('*.php');

        $directories = [];
        foreach ($this->paths as $path) {
            $fullPath = $this->basePath.'/'.$path;
            if (is_dir($fullPath)) {
                $directories[] = $fullPath;
            }
        }

        if (empty($directories)) {
            return $finder->in($this->basePath)->name('*.php');
        }

        $finder->in($directories);

        foreach ($this->exclude as $excluded) {
            $finder->notPath($excluded);
        }

        return $finder;
    }

    /**
     * Get files in a specific directory relative to base.
     *
     * @return iterable<SplFileInfo>
     */
    public function filesIn(string $directory, string $pattern = '*.php'): iterable
    {
        $fullPath = $this->basePath.'/'.$directory;

        if (! is_dir($fullPath)) {
            return [];
        }

        return (new Finder)->files()->name($pattern)->in($fullPath);
    }

    /**
     * Get file contents by relative path (cached).
     */
    public function read(string $relativePath): string
    {
        if (! isset($this->fileCache[$relativePath])) {
            $fullPath = $this->basePath.'/'.$relativePath;
            $this->fileCache[$relativePath] = file_exists($fullPath)
                ? file_get_contents($fullPath)
                : '';
        }

        return $this->fileCache[$relativePath];
    }

    /**
     * Check if a file/directory exists relative to base.
     */
    public function exists(string $relativePath): bool
    {
        return file_exists($this->basePath.'/'.$relativePath);
    }

    /**
     * Get the relative path from base for a full path.
     */
    public function relativePath(string $fullPath): string
    {
        $resolved = realpath($fullPath) ?: $fullPath;

        return ltrim(str_replace($this->resolvedBasePath, '', $resolved), '/');
    }
}
