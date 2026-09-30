<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$finder = new \App\Imports\HeaderFinderImport();
\Maatwebsite\Excel\Facades\Excel::import($finder, "data_testing_sortir_obat_v2.xlsx", "local");
echo "HEADER ROW DETECTED: " . $finder->headerRow;
