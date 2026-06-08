<?php

declare(strict_types=1);

namespace YUIPT\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Verifies that every WP_Error message string in src/ PHP files
 * is wrapped in an i18n function (__(), esc_html__(), etc.)
 * rather than a raw string literal.
 *
 * Detects patterns like:
 *   new WP_Error('code', 'raw message', ...)
 * which must instead be:
 *   new WP_Error('code', __('raw message', 'yui-preview-token'), ...)
 *
 * Handles both single-line and multi-line WP_Error() call syntax.
 */
class WpErrorI18nTest extends TestCase
{
    private const SOURCE_DIRS = [__DIR__ . '/../../src'];

    public function test_all_wp_error_messages_are_wrapped_in_i18n_function(): void
    {
        $violations = [];

        foreach ($this->php_files() as $file) {
            $content  = (string) file_get_contents($file);
            $basename = basename($file);

            // Match new WP_Error() calls where the second argument is a raw
            // single-quoted string (not preceded by __() or similar).
            // \s* with /s flag handles both inline and multi-line call syntax.
            $pattern = "/new\s+WP_Error\s*\(\s*'[^']*'\s*,\s*'([^']+)'/s";

            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[1] as [$message, $offset]) {
                    $line         = substr_count(substr($content, 0, $offset), "\n") + 1;
                    $violations[] = sprintf('%s:%d — \'%s\'', $basename, $line, $message);
                }
            }
        }

        self::assertEmpty(
            $violations,
            sprintf(
                "%d WP_Error message(s) not wrapped in an i18n function:\n%s",
                count($violations),
                implode("\n", array_map(static fn(string $v): string => "  {$v}", $violations))
            )
        );
    }

    /** @return string[] */
    private function php_files(): array
    {
        $files = [];

        foreach (self::SOURCE_DIRS as $dir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
