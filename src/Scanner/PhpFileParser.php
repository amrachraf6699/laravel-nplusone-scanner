<?php

declare(strict_types=1);

namespace Amrachraf6699\LaravelNPlusOneScanner\Scanner;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

final class PhpFileParser
{
    /** @return array{0:list<Node>,1:?ScanDiagnostic} */
    public function parse(string $file): array
    {
        try {
            $parser = (new ParserFactory())->createForNewestSupportedVersion();
            $ast = $parser->parse((string) file_get_contents($file)) ?? [];
            $traverser = new NodeTraverser();
            $traverser->addVisitor(new NameResolver(null, ['preserveOriginalNames' => true]));
            return [$traverser->traverse($ast), null];
        } catch (Error $error) {
            return [[], new ScanDiagnostic('parse_error', 'error', $error->getMessage(), $file, $error->getStartLine())];
        } catch (\Throwable $error) {
            return [[], new ScanDiagnostic('read_error', 'error', $error->getMessage(), $file)];
        }
    }
}
