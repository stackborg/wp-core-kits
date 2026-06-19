<?php
/**
 * EcosystemConsistencyTest — validates all plugins use correct core-kits namespaces.
 *
 * This test prevents namespace regressions across the entire plugin ecosystem.
 * It scans all sb-* plugin directories for incorrect import statements.
 *
 * Run from wp-core-kits directory:
 *   vendor/bin/phpunit tests/Unit/EcosystemConsistencyTest.php
 *
 * @package Stackborg\WPCoreKits\Tests\Unit
 */

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class EcosystemConsistencyTest extends TestCase
{
    /** @var string Base directory containing all plugins */
    private string $baseDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->baseDir = dirname(__DIR__, 3); // /var/www/wordpress
    }

    /**
     * @test
     * Ensures no plugin uses the non-existent Database\Database namespace.
     * The correct namespace is WordPress\Database.
     */
    public function noPluginUsesWrongDatabaseNamespace(): void
    {
        $violations = $this->scanForPattern(
            'use Stackborg\\WPCoreKits\\Database\\Database;'
        );

        $this->assertEmpty(
            $violations,
            "Found files using wrong Database namespace (should be WordPress\\Database):\n" .
            implode("\n", $violations)
        );
    }

    /**
     * @test
     * Ensures no plugin uses the non-existent WordPress\Response namespace.
     * The correct namespace is REST\Response.
     */
    public function noPluginUsesWrongResponseNamespace(): void
    {
        $violations = $this->scanForPattern(
            'use Stackborg\\WPCoreKits\\WordPress\\Response;'
        );

        $this->assertEmpty(
            $violations,
            "Found files using wrong Response namespace (should be REST\\Response):\n" .
            implode("\n", $violations)
        );
    }

    /**
     * @test
     * Ensures all plugins use Database::charsetCollate() instead of raw $wpdb.
     */
    public function noPluginUsesRawWpdbCharsetCollate(): void
    {
        $violations = $this->scanForPattern(
            'get_charset_collate()',
            ['*/Services/Database/DatabaseMigration.php']
        );

        // Filter out comments — only actual code usage is a violation
        $codeViolations = array_filter($violations, function ($entry) {
            return !str_contains($entry, '//') && !str_contains($entry, '/*') && !str_contains($entry, '* ');
        });

        $this->assertEmpty(
            $codeViolations,
            "Found DatabaseMigration files using raw \$wpdb->get_charset_collate() instead of Database::charsetCollate():\n" .
            implode("\n", $codeViolations)
        );
    }

    /**
     * @test
     * Ensures all plugins have a text domain in their __() calls.
     */
    public function noTranslationCallsMissingTextDomain(): void
    {
        $violations = [];

        foreach ($this->getPluginDirs() as $pluginDir) {
            $files = $this->getPhpFiles($pluginDir . '/src');
            foreach ($files as $file) {
                $content = file_get_contents($file);
                // Match __('text') without a second argument — missing text domain
                if (preg_match("/__\('[^']+'\)\s*[^,]/", $content)) {
                    $violations[] = str_replace($this->baseDir . '/', '', $file);
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Found files with __() calls missing text domain:\n" .
            implode("\n", $violations)
        );
    }

    /**
     * @test
     * Ensures all plugins have declare(strict_types=1) in their PHP files.
     */
    public function allPluginFilesHaveStrictTypes(): void
    {
        $violations = [];

        foreach ($this->getPluginDirs() as $pluginDir) {
            $files = $this->getPhpFiles($pluginDir . '/src');
            foreach ($files as $file) {
                $basename = basename($file);
                // Skip index.php guard files
                if ($basename === 'index.php') continue;

                $content = file_get_contents($file);
                if (!str_contains($content, 'declare(strict_types=1)')) {
                    $violations[] = str_replace($this->baseDir . '/', '', $file);
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Found files missing declare(strict_types=1):\n" .
            implode("\n", $violations)
        );
    }

    /**
     * @test
     * Ensures all plugins have consistent text domain (no typos).
     */
    public function textDomainsAreConsistent(): void
    {
        $expectedDomains = [
            'sb-accessopress' => 'sb-accessopress',
            'sb-ai-presso'    => 'sb-ai-presso',
            'sb-backuppress'  => 'sb-backuppress',
            'sb-cachepress'   => 'sb-cachepress',
            'sb-consentpress' => 'sb-consentpress',
            'sb-mailpress'    => 'sb-mailpress',
            'sb-replaypress'  => 'sb-replaypress',
            'sb-resetpress'   => 'sb-resetpress',
            'sb-supportpress' => 'sb-supportpress',
            'sb-woopress'     => 'sb-woopress',
        ];

        $violations = [];

        foreach ($this->getPluginDirs() as $pluginDir) {
            $pluginName = basename($pluginDir);
            if (!isset($expectedDomains[$pluginName])) continue;

            $expectedDomain = $expectedDomains[$pluginName];
            $files = $this->getPhpFiles($pluginDir . '/src');

            foreach ($files as $file) {
                $content = file_get_contents($file);
                // Find all text domain usages: __('text', 'domain') or esc_html__('text', 'domain')
                preg_match_all("/(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\([^,]+,\s*'([^']+)'\)/", $content, $matches);

                foreach ($matches[1] as $domain) {
                    if ($domain !== $expectedDomain && $domain !== 'default') {
                        $violations[] = sprintf(
                            "%s: Wrong text domain '%s' (expected '%s')",
                            str_replace($this->baseDir . '/', '', $file),
                            $domain,
                            $expectedDomain
                        );
                    }
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Found files with incorrect text domains:\n" .
            implode("\n", $violations)
        );
    }

    // ── Helper Methods ─────────────────────────────────

    /**
     * Scan all plugin src/ directories for a string pattern.
     *
     * @param string $pattern The string to search for
     * @param array<string> $filePatterns Optional glob patterns to restrict search
     * @return array<string> List of matching file:line entries
     */
    private function scanForPattern(string $pattern, array $filePatterns = []): array
    {
        $violations = [];

        foreach ($this->getPluginDirs() as $pluginDir) {
            $srcDir = $pluginDir . '/src';
            if (!is_dir($srcDir)) continue;

            $files = $filePatterns
                ? $this->getPhpFilesByGlob($pluginDir, $filePatterns)
                : $this->getPhpFiles($srcDir);

            foreach ($files as $file) {
                $lines = file($file);
                foreach ($lines as $lineNum => $line) {
                    if (str_contains($line, $pattern)) {
                        $violations[] = sprintf(
                            '%s:%d: %s',
                            str_replace($this->baseDir . '/', '', $file),
                            $lineNum + 1,
                            trim($line)
                        );
                    }
                }
            }
        }

        return $violations;
    }

    /** @return array<string> Plugin directory paths */
    private function getPluginDirs(): array
    {
        $dirs = glob($this->baseDir . '/sb-*', GLOB_ONLYDIR);
        return $dirs ?: [];
    }

    /** @return array<string> PHP file paths in directory */
    private function getPhpFiles(string $dir): array
    {
        if (!is_dir($dir)) return [];

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $path = $file->getPathname();
                // Skip vendor directories
                if (str_contains($path, '/vendor/')) continue;
                $files[] = $path;
            }
        }

        return $files;
    }

    /** @return array<string> PHP files matching glob patterns within a plugin dir */
    private function getPhpFilesByGlob(string $pluginDir, array $patterns): array
    {
        $files = [];
        foreach ($patterns as $pattern) {
            $matches = glob($pluginDir . '/' . $pattern);
            if ($matches) {
                $files = array_merge($files, $matches);
            }
        }
        return $files;
    }
}
