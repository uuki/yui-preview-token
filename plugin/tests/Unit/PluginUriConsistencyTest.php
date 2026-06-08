<?php

declare(strict_types=1);

namespace YUIPT\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Asserts that the Plugin URI header in the main plugin file matches
 * the HTTPS URL derived from the git remote "origin".
 *
 * Prevents regressions where the repository is renamed but the plugin
 * header still points to the old URL.
 */
class PluginUriConsistencyTest extends TestCase
{
    public function test_plugin_uri_matches_git_remote_origin(): void
    {
        $root       = dirname(__DIR__, 3);
        $pluginFile = glob($root . '/plugin/*.php')[0] ?? null;

        self::assertNotNull($pluginFile, 'No plugin bootstrap file found in plugin/');

        // Extract Plugin URI from plugin header
        $content = (string) file_get_contents($pluginFile);
        self::assertMatchesRegularExpression(
            '/^\s*\*\s*Plugin URI:/m',
            $content,
            'Plugin URI header not found in ' . basename($pluginFile)
        );

        preg_match('/^\s*\*\s*Plugin URI:\s*(.+)$/m', $content, $matches);
        $pluginUri = trim($matches[1] ?? '');
        self::assertNotEmpty($pluginUri, 'Plugin URI is empty');

        // Derive HTTPS URL from git remote "origin"
        $remoteOutput = shell_exec("git -C " . escapeshellarg($root) . " remote get-url origin 2>/dev/null");
        self::assertNotNull($remoteOutput, 'Failed to read git remote origin');

        $remote = trim((string) $remoteOutput);
        // Normalise SSH → HTTPS: git@github.com:user/repo.git → https://github.com/user/repo
        $remoteHttps = preg_replace(
            '/^git@([^:]+):(.+?)(?:\.git)?$/',
            'https://$1/$2',
            $remote
        );
        // Strip trailing .git from HTTPS URLs too
        $remoteHttps = rtrim((string) $remoteHttps, '/');
        $remoteHttps = preg_replace('/\.git$/', '', $remoteHttps);

        $pluginUriNorm = rtrim($pluginUri, '/');

        self::assertSame(
            $remoteHttps,
            $pluginUriNorm,
            sprintf(
                "Plugin URI \"%s\" does not match git remote origin \"%s\".\n" .
                "Update the Plugin URI header in %s.",
                $pluginUriNorm,
                $remoteHttps,
                basename($pluginFile)
            )
        );
    }
}
