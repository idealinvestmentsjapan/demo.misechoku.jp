<?php

// アプリの起動・DB接続・ビュー描画をせず、PHPとコンパイル済みBladeの構文を解析する。
require dirname(__DIR__, 2) . '/vendor/autoload.php';

$root = dirname(__DIR__, 2);
$app = new Illuminate\Foundation\Application($root);
$app->instance('config', new Illuminate\Config\Repository(['view' => ['paths' => [$root . '/resources/views']]]));
$files = new Illuminate\Filesystem\Filesystem();
$compiler = new Illuminate\View\Compilers\BladeCompiler($files, sys_get_temp_dir() . '/misechoku-blade-check');
$compiler->if('shopowner', fn () => false);
$factory = new Illuminate\View\Factory(
    new Illuminate\View\Engines\EngineResolver(),
    new Illuminate\View\FileViewFinder($files, [$root . '/resources/views']),
    new Illuminate\Events\Dispatcher($app)
);
$app->instance('view', $factory);
$app->instance('blade.compiler', $compiler);
Illuminate\Support\Facades\Facade::setFacadeApplication($app);

$counts = ['php' => 0, 'blade' => 0];
$errors = [];
foreach (['app', 'routes', 'config', 'tests', 'resources/views'] as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $isBlade = str_ends_with($file->getFilename(), '.blade.php');
        $counts[$isBlade ? 'blade' : 'php']++;
        try {
            $source = file_get_contents($file->getPathname());
            token_get_all($isBlade ? $compiler->compileString($source) : $source, TOKEN_PARSE);
        } catch (Throwable $error) {
            $errors[] = ['file' => str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname()), 'message' => $error->getMessage()];
        }
    }
}
echo json_encode(['checked' => $counts, 'errors' => $errors], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($errors === [] ? 0 : 1);
