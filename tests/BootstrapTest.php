<?php

namespace GlpiPlugin\Approvalbymail\Tests;

use PHPUnit\Framework\TestCase;

class BootstrapTest extends TestCase
{
    public function testPhpVersion(): void
    {
        $this->assertTrue(
            version_compare(PHP_VERSION, '8.2.0', '>='),
            'GLPI 11 requires PHP 8.2+'
        );
    }

    public function testPluginFilesExist(): void
    {
        $this->assertFileExists(__DIR__ . '/../setup.php');
        $this->assertFileExists(__DIR__ . '/../hook.php');
        $this->assertFileExists(__DIR__ . '/../front/approve.php');
    }
}
