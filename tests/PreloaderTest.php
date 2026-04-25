<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\OPCache\PreloadConfig;
use EzPhp\OPCache\Preloader;
use EzPhp\OPCache\PreloadException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Preloader::class)]
#[UsesClass(PreloadConfig::class)]
#[UsesClass(PreloadException::class)]
final class PreloaderTest extends TestCase
{
    /** @var string[] */
    private array $tempFiles = [];

    private string $tempDir = '';

    private string $outputFile = '';

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ez-php-opcache-test-' . uniqid();
        mkdir($this->tempDir, 0o755, true);

        $this->outputFile = sys_get_temp_dir() . '/preload-test-' . uniqid() . '.php';
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        if (file_exists($this->outputFile)) {
            unlink($this->outputFile);
        }

        $this->removeDirectory($this->tempDir);
    }

    public function testCollectReturnsPhpFiles(): void
    {
        $this->createFile($this->tempDir . '/Alpha.php', '<?php class Alpha {}');
        $this->createFile($this->tempDir . '/Beta.php', '<?php class Beta {}');
        $this->createFile($this->tempDir . '/gamma.txt', 'not php');

        $config = new PreloadConfig(
            outputFile: $this->outputFile,
            paths: [$this->tempDir],
        );

        $files = (new Preloader($config))->collect();

        self::assertCount(2, $files);
        self::assertContains($this->tempDir . '/Alpha.php', $files);
        self::assertContains($this->tempDir . '/Beta.php', $files);
    }

    public function testCollectExcludesPatternMatches(): void
    {
        $this->createFile($this->tempDir . '/Service.php', '<?php class Service {}');
        $this->createFile($this->tempDir . '/ServiceTest.php', '<?php class ServiceTest {}');
        $this->createFile($this->tempDir . '/BaseTestCase.php', '<?php class BaseTestCase {}');

        $config = new PreloadConfig(
            outputFile: $this->outputFile,
            paths: [$this->tempDir],
            excludePatterns: ['*Test.php', '*TestCase.php'],
        );

        $files = (new Preloader($config))->collect();

        self::assertCount(1, $files);
        self::assertContains($this->tempDir . '/Service.php', $files);
    }

    public function testCollectScansSubdirectories(): void
    {
        $subDir = $this->tempDir . '/Sub';
        mkdir($subDir, 0o755, true);

        $this->createFile($this->tempDir . '/Root.php', '<?php class Root {}');
        $this->createFile($subDir . '/Child.php', '<?php class Child {}');

        $config = new PreloadConfig(
            outputFile: $this->outputFile,
            paths: [$this->tempDir],
        );

        $files = (new Preloader($config))->collect();

        self::assertCount(2, $files);
    }

    public function testCollectIgnoresNonExistentPaths(): void
    {
        $config = new PreloadConfig(
            outputFile: $this->outputFile,
            paths: ['/this/path/does/not/exist'],
        );

        $files = (new Preloader($config))->collect();

        self::assertSame([], $files);
    }

    public function testCollectReturnsEmptyArrayWhenNoPathsConfigured(): void
    {
        $config = new PreloadConfig(outputFile: $this->outputFile);

        $files = (new Preloader($config))->collect();

        self::assertSame([], $files);
    }

    public function testCollectReturnsSortedFiles(): void
    {
        $this->createFile($this->tempDir . '/Zeta.php', '<?php class Zeta {}');
        $this->createFile($this->tempDir . '/Alpha.php', '<?php class Alpha {}');
        $this->createFile($this->tempDir . '/Mu.php', '<?php class Mu {}');

        $config = new PreloadConfig(
            outputFile: $this->outputFile,
            paths: [$this->tempDir],
        );

        $files = (new Preloader($config))->collect();

        self::assertSame(array_values($files), $files);

        $sorted = $files;
        sort($sorted);
        self::assertSame($sorted, $files);
    }

    public function testGenerateWritesOpcacheCompileFile(): void
    {
        $this->createFile($this->tempDir . '/Foo.php', '<?php class Foo {}');
        $this->createFile($this->tempDir . '/Bar.php', '<?php class Bar {}');

        $config = new PreloadConfig(
            outputFile: $this->outputFile,
            paths: [$this->tempDir],
        );

        $count = (new Preloader($config))->generate();

        self::assertSame(2, $count);
        self::assertFileExists($this->outputFile);

        $content = (string) file_get_contents($this->outputFile);
        self::assertStringContainsString('opcache_compile_file', $content);
        self::assertStringContainsString('/Bar.php', $content);
        self::assertStringContainsString('/Foo.php', $content);
        self::assertStringContainsString('Total files: 2', $content);
    }

    public function testGenerateWritesRequireOnceWhenConfigured(): void
    {
        $this->createFile($this->tempDir . '/Foo.php', '<?php class Foo {}');

        $config = new PreloadConfig(
            outputFile: $this->outputFile,
            paths: [$this->tempDir],
            useRequireOnce: true,
        );

        (new Preloader($config))->generate();

        $content = (string) file_get_contents($this->outputFile);
        self::assertStringContainsString('require_once', $content);
        self::assertStringNotContainsString('opcache_compile_file', $content);
    }

    public function testGenerateReturnsZeroWhenNothingCollected(): void
    {
        $config = new PreloadConfig(outputFile: $this->outputFile);

        $count = (new Preloader($config))->generate();

        self::assertSame(0, $count);
        self::assertFileExists($this->outputFile);
    }

    public function testGenerateThrowsWhenOutputDirectoryMissing(): void
    {
        $config = new PreloadConfig(
            outputFile: '/nonexistent/directory/preload.php',
            paths: [$this->tempDir],
        );

        $this->expectException(PreloadException::class);
        $this->expectExceptionMessageMatches('/Output directory/');

        (new Preloader($config))->generate();
    }

    private function createFile(string $path, string $content): void
    {
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $entries = scandir($dir);

        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
