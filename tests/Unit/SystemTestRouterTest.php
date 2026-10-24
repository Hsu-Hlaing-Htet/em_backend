<?php

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

test('system test router serves public assets without exposing private files or PHP source', function (string $uri, string $body, int $status): void {
    $root = sys_get_temp_dir().'/rosewood-router-'.bin2hex(random_bytes(8));
    $files = new Filesystem;

    try {
        $files->makeDirectory($root.'/scripts', 0755, true);
        $files->makeDirectory($root.'/public', 0755, true);
        $files->makeDirectory($root.'/storage/app/public', 0755, true);
        $files->copy(base_path('scripts/system_test_router.php'), $root.'/scripts/system_test_router.php');
        $files->put($root.'/public/index.php', '<?php echo "front-controller";');
        $files->put($root.'/public/asset.txt', 'public-asset');
        $files->put($root.'/public/unsafe.php', '<?php echo "private-source";');
        $files->put($root.'/.env', 'PRIVATE_FIXTURE_ONLY');
        $files->put($root.'/storage/app/public/image.txt', 'public-upload');
        symlink($root.'/storage/app/public', $root.'/public/storage');
        symlink($root.'/.env', $root.'/public/private-link.txt');

        // A temporary front controller keeps every case independent of Laravel and databases.
        $process = new Process([
            PHP_BINARY,
            '-r',
            'register_shutdown_function(function () { fwrite(STDERR, (string) (http_response_code() ?: 200)); }); $_SERVER["REQUEST_URI"] = $argv[2]; require $argv[1];',
            $root.'/scripts/system_test_router.php',
            $uri,
        ]);
        $process->mustRun();

        expect($process->getOutput())->toBe($body)
            ->and($process->getErrorOutput())->toBe((string) $status);
    } finally {
        $files->deleteDirectory($root);
    }
})->with([
    'public asset' => ['/asset.txt', 'public-asset', 200],
    'uploaded public asset' => ['/storage/image.txt', 'public-upload', 200],
    'application route' => ['/api/example', 'front-controller', 200],
    'front controller' => ['/index.php', 'front-controller', 200],
    'PHP source' => ['/unsafe.php', '', 404],
    'parent traversal' => ['/../.env', '', 404],
    'encoded traversal' => ['/%2e%2e/.env', '', 404],
    'private symlink' => ['/private-link.txt', '', 404],
    'null byte' => ['/asset.txt%00.php', '', 404],
]);
