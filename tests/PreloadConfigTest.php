<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\OPCache\PreloadConfig;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PreloadConfig::class)]
final class PreloadConfigTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $config = new PreloadConfig('/tmp/preload.php');

        self::assertSame('/tmp/preload.php', $config->getOutputFile());
        self::assertSame([], $config->getPaths());
        self::assertSame([], $config->getExcludePatterns());
        self::assertFalse($config->useRequireOnce());
    }

    public function testCustomValues(): void
    {
        $config = new PreloadConfig(
            outputFile: '/var/www/preload.php',
            paths: ['/var/www/src', '/var/www/vendor'],
            excludePatterns: ['*Test.php', '*Interface.php'],
            useRequireOnce: true,
        );

        self::assertSame('/var/www/preload.php', $config->getOutputFile());
        self::assertSame(['/var/www/src', '/var/www/vendor'], $config->getPaths());
        self::assertSame(['*Test.php', '*Interface.php'], $config->getExcludePatterns());
        self::assertTrue($config->useRequireOnce());
    }
}
