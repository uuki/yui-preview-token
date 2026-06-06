<?php

declare(strict_types=1);

namespace WPT\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPT\WordPress\Constants;

/**
 * Asserts that every hardcoded REST namespace reference in the playground
 * matches Constants::REST_NAMESPACE.
 *
 * Prevents regressions where a namespace rename in PHP is not propagated
 * to playground HTML/JS files (which caused preview 404s after the
 * wp-preview-token/v1 → preview-token/v1 rename).
 */
class RestNamespaceConsistencyTest extends TestCase
{
    /**
     * Files that hardcode the REST namespace as a URL segment.
     * Path is relative to the project root.
     *
     * @var array<string>
     */
    private const CHECKED_FILES = [
        'playground/index.html',
        'playground/e2e/preview.spec.js',
        'playground/e2e/security.spec.js',
        'playground/e2e/permissions.spec.js',
    ];

    public function test_all_playground_files_use_current_rest_namespace(): void
    {
        $namespace = Constants::REST_NAMESPACE;
        // Match the namespace as a URL segment; trailing slash is optional
        // (e.g. both "/wp-json/preview-token/v1/" and "/wp-json/preview-token/v1`" match)
        $expected  = "/wp-json/{$namespace}";
        $root      = dirname(__DIR__, 2);
        $failures  = [];

        foreach (self::CHECKED_FILES as $rel) {
            $path    = $root . '/' . $rel;
            $content = (string) file_get_contents($path);

            if (!str_contains($content, $expected)) {
                $failures[] = $rel;
            }
        }

        self::assertEmpty(
            $failures,
            sprintf(
                "The following file(s) do not contain the expected REST namespace string \"%s\" (as a URL segment):\n%s\n\n"
                . "If Constants::REST_NAMESPACE was renamed, update these files too.",
                $expected,
                implode("\n", array_map(fn($f) => "  - {$f}", $failures))
            )
        );
    }
}
