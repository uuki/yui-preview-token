<?php

declare(strict_types=1);

namespace YUIPT\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Ensures that no PHP file in the plugin retains a reference to the
 * old PVT\ namespace after the prefix rename to DRPT\.
 *
 * Catches cases where a simple namespace/use sed replacement misses
 * fully-qualified class references in non-declaration positions,
 * e.g. PVT\WordPress\Plugin::get_instance() in the bootstrap file.
 */
class NamespaceRenameConsistencyTest extends TestCase
{
    /** Files to scan: bootstrap + all source PHP files. */
    private function php_files(): array
    {
        $root  = dirname(__DIR__, 3);
        $files = [
            $root . '/plugin/yui-preview-token.php',
        ];

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(__DIR__ . '/../../src')
        );
        foreach ($iter as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    public function test_no_php_file_contains_old_pvt_namespace_reference(): void
    {
        $violations = [];

        foreach ($this->php_files() as $path) {
            $content  = (string) file_get_contents($path);
            $relative = str_replace(dirname(__DIR__, 3) . '/', '', $path);

            // Match any PVT\ occurrence that is not inside a comment or string
            // (simple heuristic: just flag any PVT\ in the file)
            if (preg_match('/\bPVT\\\\/', $content, $match, PREG_OFFSET_CAPTURE)) {
                $line = substr_count(substr($content, 0, (int) $match[0][1]), "\n") + 1;
                $violations[] = "{$relative}:{$line}";
            }
        }

        self::assertEmpty(
            $violations,
            "The following file(s) still contain a 'PVT\\\\' namespace reference:\n"
            . implode("\n", array_map(fn($v) => "  - {$v}", $violations))
            . "\n\nRun the namespace rename sed command and update all PVT\\ references."
        );
    }
}
