<?php

$root = dirname(__DIR__);
$packages = [
    'twbs/bootstrap' => [
        'name' => 'Bootstrap',
        'target' => 'bootstrap',
        'files' => [
            'dist/css/bootstrap.min.css' => 'css/bootstrap.min.css',
            'dist/css/bootstrap.min.css.map' => 'css/bootstrap.min.css.map',
            'dist/js/bootstrap.bundle.min.js' => 'js/bootstrap.bundle.min.js',
            'dist/js/bootstrap.bundle.min.js.map' => 'js/bootstrap.bundle.min.js.map',
            'LICENSE' => 'LICENSE',
        ],
    ],
    'akaunting/laravel-apexcharts' => [
        'name' => 'ApexCharts',
        'target' => 'apexcharts',
        'files' => [
            'src/Public/apexcharts.js' => 'apexcharts.js',
            'LICENSE.md' => 'LICENSE.md',
        ],
    ],
];

foreach ($packages as $package => $assets) {
    foreach ($assets['files'] as $source => $destination) {
        if (! is_file($root.'/vendor/'.$package.'/'.$source)) {
            fwrite(STDERR, "{$assets['name']} absent ou incomplet. Exécutez composer install.\n");
            exit(1);
        }
    }
}

foreach ($packages as $package => $assets) {
    foreach ($assets['files'] as $source => $destination) {
        $target = $root.'/public/vendor/'.$assets['target'].'/'.$destination;
        $directory = dirname($target);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            fwrite(STDERR, "Impossible de créer le dossier des ressources {$assets['name']}.\n");
            exit(1);
        }

        if (! copy($root.'/vendor/'.$package.'/'.$source, $target)) {
            fwrite(STDERR, "Impossible de publier la ressource {$assets['name']} : {$destination}\n");
            exit(1);
        }
    }

    fwrite(STDOUT, "{$assets['name']} publié localement dans public/vendor/{$assets['target']}.\n");
}
