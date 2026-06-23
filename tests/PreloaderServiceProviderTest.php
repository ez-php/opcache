<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\OPCache\PreloadConfig;
use EzPhp\OPCache\Preloader;
use EzPhp\OPCache\PreloaderServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Support\FakeConfig;
use Tests\Support\FakeContainer;

/**
 * Smoke test: PreloaderServiceProvider registers its bindings in a minimal
 * container context without error.
 *
 * @uses \Tests\Support\FakeConfig
 * @uses \Tests\Support\FakeContainer
 */
#[CoversClass(PreloaderServiceProvider::class)]
#[UsesClass(PreloadConfig::class)]
#[UsesClass(Preloader::class)]
final class PreloaderServiceProviderTest extends TestCase
{
    public function test_register_binds_preload_config_and_preloader(): void
    {
        $container = new FakeContainer(new FakeConfig([]));
        $provider = new PreloaderServiceProvider($container);

        $provider->register();
        $provider->boot(); // no-op, must not throw

        $this->assertTrue($container->wasBound(PreloadConfig::class));
        $this->assertTrue($container->wasBound(Preloader::class));
        $this->assertInstanceOf(PreloadConfig::class, $container->make(PreloadConfig::class));
        $this->assertInstanceOf(Preloader::class, $container->make(Preloader::class));
    }
}
