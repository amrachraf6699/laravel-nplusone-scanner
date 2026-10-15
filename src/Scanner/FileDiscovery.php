<?php

declare(strict_types=1);

namespace Amrachraf6699\LaravelNPlusOneScanner\Scanner;

use Symfony\Component\Finder\Finder;

final class FileDiscovery
{
    /** @return list<string> */
    public function discover(array $paths, array $exclude, string $basePath): array
    {
        $files = [];
        foreach ($paths as $path) {
            $root = $this->absolute($path, $basePath);
            if (is_file($root)) {
                if (str_ends_with(strtolower($root), '.php')) {
                    $files[] = realpath($root) ?: $root;
                }
                continue;
            }
            if (!is_dir($root)) {
                continue;
            }
            $finder = Finder::create()->files()->name('*.php')->ignoreUnreadableDirs()->in($root);
            foreach ($exclude as $excluded) {
                $finder->exclude($excluded);
            }
            foreach ($finder as $file) {
                $files[] = $file->getRealPath() ?: $file->getPathname();
            }
        }
        $files = array_values(array_unique($files));
        sort($files);
        return $files;
    }

    private function absolute(string $path, string $basePath): string
    {
        if (preg_match('/^(?:[A-Za-z]:[\\\\]|[\\\\\\/])/', $path)) {
            return $path;
        }
        return rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }
}
