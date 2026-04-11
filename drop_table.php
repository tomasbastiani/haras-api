<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

try {
    Schema::dropIfExists('user_notifications');
    echo "Table user_notifications dropped if it existed.\n";
} catch (\Exception $e) {
    echo $e->getMessage();
}
