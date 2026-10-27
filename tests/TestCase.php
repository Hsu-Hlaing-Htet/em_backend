<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;
use Tests\Support\TestDatabaseSafety;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $connection = $app['db']->connection();
        TestDatabaseSafety::assertConfiguration(
            $connection->getConfig(),
            $app->environment(),
            filter_var(env('TEST_DATABASE_DISPOSABLE', false), FILTER_VALIDATE_BOOLEAN),
        );
        if ($connection->getDriverName() === 'mysql') {
            $identity = $connection->selectOne('SELECT DATABASE() AS db, @@port AS port');
            if ($identity->db !== 'rosewood_test'
                || (int) $identity->port !== (int) $connection->getConfig('port')) {
                throw new RuntimeException('The connected test database does not match the isolated configuration.');
            }
        }

        // This runs before Laravel setUpTraits(), including RefreshDatabase.
        $this->isolateTestFiles($app);

        return $app;
    }

    private function isolateTestFiles(\Illuminate\Foundation\Application $app): void
    {
        $base = realpath($app->basePath());
        $root = (string) env('TEST_STORAGE_ROOT', $base.'/storage/framework/testing/isolated-'.getmypid());
        $allowed = str_starts_with($root, $base.'/.test-verification/')
            || str_starts_with($root, $base.'/storage/framework/testing/');
        if (! $allowed || str_contains($root, '..') || is_link($root)) {
            throw new RuntimeException('Test storage must be isolated inside the backend test runtime.');
        }
        foreach (['app/public', 'app/private', 'framework/cache/data', 'framework/sessions',
            'framework/views', 'logs', 'public/images'] as $directory) {
            if (! is_dir($root.'/'.$directory)) {
                mkdir($root.'/'.$directory, 0755, true);
            }
        }
        $sourceImages = $app->publicPath('images');
        foreach (glob($sourceImages.'/*') ?: [] as $source) {
            if (is_file($source) && ! is_link($source)) {
                copy($source, $root.'/public/images/'.basename($source));
            }
        }
        $app->useStoragePath($root);
        $app->usePublicPath($root.'/public');
        $app['config']->set([
            'filesystems.disks.local.root' => $root.'/app/private',
            'filesystems.disks.public.root' => $root.'/app/public',
            'filesystems.links' => [$root.'/public/storage' => $root.'/app/public'],
            'view.compiled' => $root.'/framework/views',
            'cache.stores.file.path' => $root.'/framework/cache/data',
            'session.files' => $root.'/framework/sessions',
            'logging.channels.single.path' => $root.'/logs/laravel.log',
            'logging.channels.daily.path' => $root.'/logs/laravel.log',
        ]);
    }
}
