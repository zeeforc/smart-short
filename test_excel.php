<?php

use App\Imports\HeaderFinderImport;
use Illuminate\Contracts\Console\Kernel;
use Maatwebsite\Excel\Facades\Excel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$finder = new HeaderFinderImport;
Excel::import($finder, 'data_testing_sortir_obat_v2.xlsx', 'local');
echo 'HEADER ROW DETECTED: '.$finder->headerRow;
