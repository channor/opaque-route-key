<?php

declare(strict_types=1);

$autoloadPaths = [
    __DIR__.'/../vendor/autoload.php',
    __DIR__.'/../../../vendor/autoload.php',
];

foreach ($autoloadPaths as $autoloadPath) {
    if (is_file($autoloadPath)) {
        require $autoloadPath;

        return;
    }
}

fwrite(STDERR, "Unable to locate Composer autoload.php for opaque-route-key tests.\n");

exit(1);
