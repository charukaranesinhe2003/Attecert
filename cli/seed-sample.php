<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/database/seeders/DatabaseSeeder.php';
require dirname(__DIR__) . '/database/seeders/SampleDataSeeder.php';
require dirname(__DIR__) . '/bootstrap/app.php';

(new Database\Seeders\SampleDataSeeder(app(App\Core\Database::class)))->run();
