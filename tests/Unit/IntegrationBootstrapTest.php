<?php

/**
 * Refuse configured WordPress copies before the destructive test installer.
 *
 * @command  vendor/bin/pest tests/Unit/IntegrationBootstrapTest.php
 */

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('refuses site configuration without parsing its database expression', function (string $location, string $configuration): void {
    // Create only a disposable core marker, never a real site or database.
    $directory = sys_get_temp_dir().'/synd-guard-'.bin2hex(random_bytes(8));
    mkdir($directory.'/core', 0700, true);
    $settings = $directory.'/core/wp-settings.php';
    $config = $directory.($location === 'parent' ? '' : '/core').'/wp-config.php';
    file_put_contents($settings, '<?php');
    file_put_contents($config, $configuration);

    // Run the actual bootstrap; a closed port prevents any database access.
    try {
        $process = new Process([PHP_BINARY, __DIR__.'/../Integration/bootstrap.php'], env: [
            'WP_TESTS_ABSPATH' => $directory.'/core',
            'WP_TESTS_DB_NAME' => 'syndication_tests',
            'WP_TESTS_DB_HOST' => '127.0.0.1:1',
        ], timeout: 10);
        $process->run();
        expect($process->getExitCode())->toBe(1)
            ->and($process->getErrorOutput())->toContain('Refusing a configured WordPress fixture', $config)
            ->and($process->getOutput())->toBe('');
    } finally {
        // Remove only this test's temporary files and empty directories.
        unlink($config);
        unlink($settings);
        rmdir($directory.'/core');
        rmdir($directory);
    }
})->with([
    'literal site database' => ['core', "<?php define('DB_NAME', 'syndication_tests');"],
    'valid spaced call' => ['core', "<?php define ('DB_NAME', 'syndication_tests');"],
    'environment-derived database' => ['core', "<?php define('DB_NAME', getenv('SITE_DATABASE'));"],
    'parent configuration' => ['parent', "<?php define('DB_NAME', 'syndication_tests');"],
]);
