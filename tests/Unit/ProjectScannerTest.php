<?php

declare(strict_types=1);

namespace Amrachraf6699\LaravelNPlusOneScanner\Tests\Unit;

use Amrachraf6699\LaravelNPlusOneScanner\Findings\Severity;
use Amrachraf6699\LaravelNPlusOneScanner\Scanner\FileDiscovery;
use Amrachraf6699\LaravelNPlusOneScanner\Scanner\PhpFileParser;
use Amrachraf6699\LaravelNPlusOneScanner\Scanner\ProjectScanner;
use Amrachraf6699\LaravelNPlusOneScanner\Scanner\ScanOptions;
use PHPUnit\Framework\TestCase;

final class ProjectScannerTest extends TestCase
{
    public function testItDetectsPropertyAndQueryCandidatesWithoutADatabase(): void
    {
        $root = dirname(__DIR__) . '/Fixtures/Project';
        $options = new ScanOptions(['app'], [], [], ['N1' => true, 'N2' => true, 'N3' => true, 'N4' => true], Severity::LOW);
        $result = (new ProjectScanner())->scan($options, $root);
        $rules = array_map(static fn ($finding) => $finding->rule, $result->findings);
        self::assertSame('complete', $result->status);
        self::assertContains('N1', $rules);
        self::assertContains('N2', $rules);
        self::assertContains('N3', $rules);
        self::assertContains('N4', $rules);
        self::assertNotEmpty($result->findings);
    }

    public function testEagerLoadedRelationshipIsNotReported(): void
    {
        $root = dirname(__DIR__) . '/Fixtures/Project';
        $options = new ScanOptions(['app/Http/Controllers/GoodController.php'], ['app'], [], ['N1' => true, 'N2' => true, 'N3' => true, 'N4' => true], Severity::LOW);
        $result = (new ProjectScanner())->scan($options, $root);
        self::assertNotContains('N1', array_map(static fn ($finding) => $finding->rule, $result->findings));
    }

    public function testParserReportsInvalidPhp(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'nplusone') . '.php';
        file_put_contents($file, '<?php class Broken {');
        [, $diagnostic] = (new PhpFileParser())->parse($file);
        @unlink($file);
        self::assertNotNull($diagnostic);
        self::assertSame('parse_error', $diagnostic->code);
    }

    public function testDiscoveryAppliesPhpFilterAndDeduplicates(): void
    {
        $root = dirname(__DIR__) . '/Fixtures/Project';
        $files = (new FileDiscovery())->discover(['app', 'app/Models'], [], $root);
        self::assertCount(count(array_unique($files)), $files);
        self::assertNotEmpty($files);
        self::assertSame([], array_filter($files, static fn ($file) => pathinfo($file, PATHINFO_EXTENSION) !== 'php'));
    }

    public function testItUsesTransitiveModelsAndRecognizesNativeIndexedLoops(): void
    {
        $root = dirname(__DIR__) . '/Fixtures/Project';
        $options = new ScanOptions(['app/Http/Controllers/RemediationController.php'], ['app'], [], ['N1' => true, 'N2' => true, 'N3' => true, 'N4' => true], Severity::LOW);
        $result = (new ProjectScanner())->scan($options, $root);
        $findings = $result->findings;
        self::assertSame(3, count(array_filter($findings, static fn ($finding) => $finding->line <= 22)));
        self::assertGreaterThanOrEqual(3, count(array_filter($findings, static fn ($finding) => $finding->confidence->value === 'medium')));
    }

    public function testAggregateAttributesAreSafeButRelationshipPropertiesRemainCandidates(): void
    {
        $root = dirname(__DIR__) . '/Fixtures/Project';
        $options = new ScanOptions(['app/Http/Controllers/RemediationController.php'], ['app'], [], ['N1' => true, 'N2' => true, 'N3' => true, 'N4' => true], Severity::LOW);
        $result = (new ProjectScanner())->scan($options, $root);
        $postsFindings = array_filter($result->findings, static fn ($finding) => $finding->relationship === 'posts');
        self::assertNotEmpty($postsFindings);
        self::assertSame([], array_filter($postsFindings, static fn ($finding) => $finding->line === 29));
    }

    public function testAmbiguousBareModelNamesDoNotResolve(): void
    {
        $index = new \Amrachraf6699\LaravelNPlusOneScanner\Analysis\SourceIndex();
        $index->addModel(new \Amrachraf6699\LaravelNPlusOneScanner\Analysis\ModelMetadata('One\\User', null));
        $index->addModel(new \Amrachraf6699\LaravelNPlusOneScanner\Analysis\ModelMetadata('Two\\User', null));
        self::assertNull($index->model('User'));
    }

    public function testOverlappingMetadataPathsReuseParsedAsts(): void
    {
        $root = dirname(__DIR__) . '/Fixtures/Project';
        $options = new ScanOptions(['app'], ['app', 'app/Models'], [], ['N1' => true, 'N2' => true, 'N3' => true, 'N4' => true], Severity::LOW);
        $result = (new ProjectScanner())->scan($options, $root);
        self::assertSame($result->filesAnalyzed, $result->cacheHits);
        self::assertSame($result->filesParsed, $result->filesDiscovered);
    }
}
