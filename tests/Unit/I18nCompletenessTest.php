<?php

declare(strict_types=1);

namespace WPT\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Verifies that every translatable string in src/ PHP files
 * has a corresponding msgid in each shipped PO file.
 *
 * Run: vendor/bin/phpunit --filter I18nCompleteness
 */
class I18nCompletenessTest extends TestCase
{
    private const TEXT_DOMAIN = 'wp-preview-token';

    private const SOURCE_DIRS = [
        __DIR__ . '/../../src',
    ];

    private const PO_FILES = [
        'ja'    => __DIR__ . '/../../languages/wp-preview-token-ja.po',
        'zh_CN' => __DIR__ . '/../../languages/wp-preview-token-zh_CN.po',
    ];

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_all_strings_present_in_ja_po(): void
    {
        $this->assert_locale_complete('ja');
    }

    public function test_all_strings_present_in_zh_CN_po(): void
    {
        $this->assert_locale_complete('zh_CN');
    }

    // ── Assertions ────────────────────────────────────────────────────────────

    private function assert_locale_complete(string $locale): void
    {
        $source  = $this->extract_source_strings();
        $po      = $this->parse_po_msgids(self::PO_FILES[$locale]);
        $missing = array_values(array_diff($source, $po));

        self::assertEmpty(
            $missing,
            sprintf(
                "%d string(s) missing from %s.po:\n%s",
                count($missing),
                $locale,
                implode("\n", array_map(fn(string $s): string => "  - \"{$s}\"", $missing))
            )
        );
    }

    // ── Extraction ────────────────────────────────────────────────────────────

    /**
     * Returns all unique translatable strings found in src/ PHP files
     * for the plugin's text domain.
     *
     * Recognises:
     *   __( 'string', 'domain' )
     *   _e( 'string', 'domain' )
     *   esc_html__( 'string', 'domain' )
     *   esc_html_e( 'string', 'domain' )
     *   esc_attr__( 'string', 'domain' )
     *   esc_attr_e( 'string', 'domain' )
     *
     * @return string[]
     */
    private function extract_source_strings(): array
    {
        $domain  = preg_quote(self::TEXT_DOMAIN, '/');
        // Match single-quoted string argument followed by the text domain.
        // Handles escaped single quotes inside the string via the alternation.
        $pattern = '/(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\s*\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'[^)]*\''. $domain . '\'/';

        $strings = [];

        foreach ($this->php_files() as $file) {
            $content = (string) file_get_contents($file);
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $raw) {
                    // Unescape PHP \' → '
                    $strings[] = str_replace("\\'", "'", $raw);
                }
            }
        }

        return array_values(array_unique($strings));
    }

    /**
     * Parses a PO file and returns all non-empty msgid values.
     *
     * @return string[]
     */
    private function parse_po_msgids(string $path): array
    {
        $content = (string) file_get_contents($path);
        $msgids  = [];

        // Match simple single-line msgid "..." entries.
        // Multi-line msgid (rare in this codebase) would need a more complex parser;
        // our source strings are all single-line, so this is sufficient.
        if (preg_match_all('/^msgid "((?:[^"\\\\]|\\\\.)*)"$/m', $content, $matches)) {
            foreach ($matches[1] as $raw) {
                if ($raw === '') {
                    continue; // Skip the file-header empty msgid
                }
                // Unescape PO \" → "  and  \n → literal \n (we don't use multiline)
                $msgids[] = str_replace('\\"', '"', $raw);
            }
        }

        return $msgids;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

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
