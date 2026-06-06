<?php

declare(strict_types=1);

namespace PVT\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Verifies that every translatable string containing printf-style placeholders
 * (%s, %d, %1$s, …) is accompanied by a "translators:" comment on the
 * immediately preceding line.
 *
 * This mirrors the WordPress.WP.I18n.MissingTranslatorsComment PHPCS rule so
 * the mistake is caught by `vendor/bin/phpunit` without needing the full PHPCS
 * stack.
 *
 * Checked functions: __(), _e(), _x(), _ex(),
 *                   esc_html__(), esc_html_e(), esc_html_x(),
 *                   esc_attr__(), esc_attr_e(), esc_attr_x()
 */
class TranslatorsCommentTest extends TestCase
{
    private const SOURCE_DIRS = [__DIR__ . '/../../src'];

    /** Matches i18n function name followed by an opening paren. */
    private const I18N_FUNC_RE =
        '/\b(?:esc_html__|esc_html_e|esc_attr__|esc_attr_e|__(?!CLASS)|_e|_x|_ex)\s*\(/';

    /**
     * Matches a printf-style placeholder inside a string literal.
     * Handles: %s  %d  %f  %1$s  %2$d  — but NOT %% (escaped literal %).
     */
    private const PLACEHOLDER_RE = '/(?<!%)%(?:[0-9]+\$)?[sdfFeEgGxXoubc]/';

    /** Matches a translators comment (single- or multi-line style). */
    private const TRANSLATORS_RE = '/(?:\/\/|\/\*)\s*translators\s*:/i';

    public function test_placeholder_strings_have_translators_comment(): void
    {
        $violations = [];

        foreach ($this->php_files() as $file) {
            $violations = array_merge($violations, $this->check_file($file));
        }

        self::assertEmpty(
            $violations,
            sprintf(
                "%d violation(s) — translatable string with placeholder(s) missing "
                . "a \"translators:\" comment on the immediately preceding line:\n%s",
                count($violations),
                implode("\n", $violations)
            )
        );
    }

    // ── Implementation ────────────────────────────────────────────────────────

    /**
     * @return string[]  List of "file:line — message" violation strings.
     */
    private function check_file(string $path): array
    {
        $lines      = explode("\n", (string) file_get_contents($path));
        $rel        = $this->relative($path);
        $violations = [];

        foreach ($lines as $idx => $line) {
            // Skip lines that don't contain an i18n function call
            if (!preg_match(self::I18N_FUNC_RE, $line)) {
                continue;
            }

            // Extract the string argument(s) from the line
            if (!preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $line, $strings)) {
                continue;
            }

            foreach ($strings[1] as $str) {
                // Skip if the string contains no printf placeholder
                if (!preg_match(self::PLACEHOLDER_RE, $str)) {
                    continue;
                }

                // The translators comment must appear on the immediately
                // preceding line (line index $idx - 1).
                $prevLine = $idx > 0 ? $lines[$idx - 1] : '';
                if (!preg_match(self::TRANSLATORS_RE, $prevLine)) {
                    $lineNo      = $idx + 1; // 1-based
                    $violations[] = "  {$rel}:{$lineNo} — \"{$str}\"";
                }
            }
        }

        return $violations;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @return string[] */
    private function php_files(): array
    {
        $files = [];
        foreach (self::SOURCE_DIRS as $dir) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($it as $f) {
                if ($f instanceof \SplFileInfo && $f->getExtension() === 'php') {
                    $files[] = $f->getPathname();
                }
            }
        }
        return $files;
    }

    private function relative(string $abs): string
    {
        $root = dirname(__DIR__, 2) . '/';
        return str_starts_with($abs, $root) ? substr($abs, strlen($root)) : $abs;
    }
}
