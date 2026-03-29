<?php

declare(strict_types=1);

namespace EzPhp\OPCache;

use EzPhp\Contracts\ConfigInterface;
use EzPhp\Contracts\ContainerInterface;
use EzPhp\Contracts\ServiceProvider;

/**
 * Class PreloaderServiceProvider
 *
 * Binds the OPcache Preloader and its configuration into the application container.
 *
 * Register in provider/modules.php:
 *
 *   $app->register(PreloaderServiceProvider::class);
 *
 * Configuration keys (config/opcache.php or environment):
 *
 *   opcache.output_file    — absolute path for the generated script (required)
 *   opcache.paths          — array of directories to scan
 *   opcache.exclude        — array of filename glob patterns to skip
 *   opcache.require_once   — bool, use require_once instead of opcache_compile_file
 *
 * @package EzPhp\OPCache
 */
final class PreloaderServiceProvider extends ServiceProvider
{
    /**
     * Bind PreloadConfig and Preloader into the container.
     */
    public function register(): void
    {
        $this->app->bind(PreloadConfig::class, function (ContainerInterface $app): PreloadConfig {
            $config = null;

            try {
                $config = $app->make(ConfigInterface::class);
            } catch (\Throwable) {
                // Config not bound — fall through to defaults.
            }

            $outputFile = $config?->get('opcache.output_file', '');
            $paths = $config?->get('opcache.paths', []);
            $excludePatterns = $config?->get('opcache.exclude', []);
            $useRequireOnce = $config?->get('opcache.require_once', false);

            return new PreloadConfig(
                outputFile: is_string($outputFile) ? $outputFile : '',
                paths: is_array($paths) ? array_values(array_filter($paths, 'is_string')) : [],
                excludePatterns: is_array($excludePatterns) ? array_values(array_filter($excludePatterns, 'is_string')) : [],
                useRequireOnce: is_bool($useRequireOnce) ? $useRequireOnce : false,
            );
        });

        $this->app->bind(Preloader::class, function (ContainerInterface $app): Preloader {
            return new Preloader($app->make(PreloadConfig::class));
        });
    }

    /**
     * Nothing to boot — this module has no HTTP endpoint and no console commands.
     */
    public function boot(): void
    {
    }
}
