<?php

declare(strict_types=1);

namespace EzPhp\OPCache;

/**
 * Class PreloadConfig
 *
 * Value object holding all configuration for the OPcache preload generator.
 *
 * @package EzPhp\OPCache
 */
final class PreloadConfig
{
    /**
     * @param string   $outputFile       Absolute path to the generated preload script.
     * @param string[] $paths            Directories to scan for PHP files.
     * @param string[] $excludePatterns  Filename glob patterns to exclude (e.g. '*Test.php').
     * @param bool     $useRequireOnce   Use require_once instead of opcache_compile_file.
     */
    public function __construct(
        private readonly string $outputFile,
        private readonly array $paths = [],
        private readonly array $excludePatterns = [],
        private readonly bool $useRequireOnce = false,
    ) {
    }

    /**
     * Returns the absolute path of the generated preload script.
     */
    public function getOutputFile(): string
    {
        return $this->outputFile;
    }

    /**
     * Returns the list of directories to scan.
     *
     * @return string[]
     */
    public function getPaths(): array
    {
        return $this->paths;
    }

    /**
     * Returns the list of filename glob patterns to exclude.
     *
     * @return string[]
     */
    public function getExcludePatterns(): array
    {
        return $this->excludePatterns;
    }

    /**
     * Returns true when require_once should be used instead of opcache_compile_file.
     */
    public function useRequireOnce(): bool
    {
        return $this->useRequireOnce;
    }
}
