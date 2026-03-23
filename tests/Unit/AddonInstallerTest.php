<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\Addon\AddonInstaller;
use Stackborg\WPCoreKits\Addon\AddonRegistry;
use Stackborg\WPCoreKits\Addon\AddonRemover;
use Stackborg\WPCoreKits\Addon\AddonUpdater;
use Stackborg\WPCoreKits\Addon\InstallResult;
use Stackborg\WPCoreKits\Contracts\AddonInterface;

class AddonInstallerTest extends TestCase
{
    private string $testDir;
    private string $addonsDir;

    protected function setUp(): void
    {
        global $wp_options;
        $wp_options = [];

        $this->testDir = sys_get_temp_dir() . '/sb_installer_test_' . uniqid();
        $this->addonsDir = $this->testDir . '/addons';
        mkdir($this->addonsDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->cleanDir($this->testDir);
    }

    private function cleanDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }

    private function createTestZip(string $slug, string $version = '1.0.0', string $type = 'free'): string
    {
        $zipPath = $this->testDir . "/{$slug}.zip";
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);

        $addonJson = json_encode([
            'slug'    => $slug,
            'name'    => ucfirst($slug),
            'version' => $version,
            'type'    => $type,
        ]);
        $zip->addFromString("{$slug}/addon.json", $addonJson);
        $zip->addFromString("{$slug}/src/init.php", "<?php // addon init");
        $zip->close();

        return $zipPath;
    }

    // ─── InstallResult ──────────────────────────────────

    public function testInstallResultOk(): void
    {
        $result = InstallResult::ok('Done', null);
        $this->assertTrue($result->success);
        $this->assertSame('Done', $result->message);
    }

    public function testInstallResultFail(): void
    {
        $result = InstallResult::fail('Error', ['detail']);
        $this->assertFalse($result->success);
        $this->assertSame(['detail'], $result->errors);
    }

    // ─── Install from local zip ─────────────────────────

    public function testInstallFromZipSuccess(): void
    {
        $zipPath = $this->createTestZip('templates');
        $registry = new AddonRegistry('test_state');
        $installer = new AddonInstaller($registry, $this->addonsDir, '1.0.0', '1.0.0');

        $result = $installer->installFromZip($zipPath);

        $this->assertTrue($result->success);
        $this->assertTrue($registry->isInstalled('templates'));
        $this->assertTrue($registry->isActive('templates'));
        $this->assertDirectoryExists($this->addonsDir . '/templates');
        $this->assertFileExists($this->addonsDir . '/templates/addon.json');
    }

    public function testInstallFromZipWithValidChecksum(): void
    {
        $zipPath = $this->createTestZip('checked');
        $checksum = hash_file('sha256', $zipPath);
        $registry = new AddonRegistry('test_state');
        $installer = new AddonInstaller($registry, $this->addonsDir, '1.0.0', '1.0.0');

        $result = $installer->installFromZip($zipPath, $checksum);
        $this->assertTrue($result->success);
    }

    public function testInstallFromZipWithBadChecksum(): void
    {
        $zipPath = $this->createTestZip('bad-check');
        $registry = new AddonRegistry('test_state');
        $installer = new AddonInstaller($registry, $this->addonsDir, '1.0.0', '1.0.0');

        $result = $installer->installFromZip($zipPath, 'invalid_checksum');
        $this->assertFalse($result->success);
        $this->assertStringContainsString('Checksum', $result->message);
    }

    public function testInstallFromZipMissingFile(): void
    {
        $registry = new AddonRegistry('test_state');
        $installer = new AddonInstaller($registry, $this->addonsDir, '1.0.0', '1.0.0');

        $result = $installer->installFromZip('/tmp/definitely_not_exists.zip');
        $this->assertFalse($result->success);
    }

    public function testInstallFromZipIncompatibleVersion(): void
    {
        $zipPath = $this->createTestZip('incompat');

        // Create zip with high core requirement
        $zipPath2 = $this->testDir . '/incompat2.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath2, \ZipArchive::CREATE);
        $zip->addFromString('incompat2/addon.json', json_encode([
            'slug' => 'incompat2', 'name' => 'Incompat', 'version' => '1.0.0',
            'requires' => ['core' => '>=99.0.0'],
        ]));
        $zip->close();

        $registry = new AddonRegistry('test_state');
        $installer = new AddonInstaller($registry, $this->addonsDir, '1.0.0', '1.0.0');

        $result = $installer->installFromZip($zipPath2);
        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->errors);
    }

    public function testInstallOverwritesExisting(): void
    {
        $zipPath = $this->createTestZip('overwrite', '1.0.0');
        $registry = new AddonRegistry('test_state');
        $installer = new AddonInstaller($registry, $this->addonsDir, '1.0.0', '1.0.0');

        $installer->installFromZip($zipPath);
        $this->assertTrue($registry->isInstalled('overwrite'));

        // Install again — should overwrite
        $zipPath2 = $this->createTestZip('overwrite', '2.0.0');
        $result = $installer->installFromZip($zipPath2);
        $this->assertTrue($result->success);
    }

    // ─── Remover ────────────────────────────────────────

    public function testUninstallRemovesAddon(): void
    {
        // Install first
        $zipPath = $this->createTestZip('removable');
        $registry = new AddonRegistry('test_state');
        $installer = new AddonInstaller($registry, $this->addonsDir, '1.0.0', '1.0.0');
        $installer->installFromZip($zipPath);

        $this->assertTrue($registry->isInstalled('removable'));

        // Uninstall
        $remover = new AddonRemover($registry, $this->addonsDir);
        $result = $remover->uninstall('removable');

        $this->assertTrue($result->success);
        $this->assertFalse($registry->isInstalled('removable'));
        $this->assertDirectoryDoesNotExist($this->addonsDir . '/removable');
    }

    public function testUninstallNonExistent(): void
    {
        $registry = new AddonRegistry('test_state');
        $remover = new AddonRemover($registry, $this->addonsDir);

        $result = $remover->uninstall('ghost');
        $this->assertFalse($result->success);
    }

    // ─── Updater ────────────────────────────────────────

    public function testCheckUpdatesFindsNewVersion(): void
    {
        $registry = new AddonRegistry('test_state');
        $zipPath = $this->createTestZip('updatable', '1.0.0');
        $installer = new AddonInstaller($registry, $this->addonsDir, '1.0.0', '1.0.0');
        $installer->installFromZip($zipPath);

        $updater = new AddonUpdater($registry, $installer, $this->addonsDir);
        $updates = $updater->checkUpdates([
            'updatable' => ['version' => '2.0.0', 'download_url' => 'https://x.com/v2.zip'],
        ]);

        $this->assertArrayHasKey('updatable', $updates);
        $this->assertSame('1.0.0', $updates['updatable']['current']);
        $this->assertSame('2.0.0', $updates['updatable']['available']);
    }

    public function testCheckUpdatesSkipsUpToDate(): void
    {
        $registry = new AddonRegistry('test_state');
        $zipPath = $this->createTestZip('current', '2.0.0');
        $installer = new AddonInstaller($registry, $this->addonsDir, '1.0.0', '1.0.0');
        $installer->installFromZip($zipPath);

        $updater = new AddonUpdater($registry, $installer, $this->addonsDir);
        $updates = $updater->checkUpdates([
            'current' => ['version' => '2.0.0', 'download_url' => 'https://x.com/v2.zip'],
        ]);

        $this->assertEmpty($updates);
    }

    public function testCheckUpdatesSkipsNonInstalled(): void
    {
        $registry = new AddonRegistry('test_state');
        $installer = new AddonInstaller($registry, $this->addonsDir, '1.0.0', '1.0.0');

        $updater = new AddonUpdater($registry, $installer, $this->addonsDir);
        $updates = $updater->checkUpdates([
            'unknown' => ['version' => '1.0.0', 'download_url' => 'https://x.com'],
        ]);

        $this->assertEmpty($updates);
    }

    public function testUpdateNonInstalledFails(): void
    {
        $registry = new AddonRegistry('test_state');
        $installer = new AddonInstaller($registry, $this->addonsDir, '1.0.0', '1.0.0');
        $updater = new AddonUpdater($registry, $installer, $this->addonsDir);

        $result = $updater->update('ghost', 'https://x.com/ghost.zip');
        $this->assertFalse($result->success);
    }
}
