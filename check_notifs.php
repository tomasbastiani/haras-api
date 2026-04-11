<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\UserNotification;
use App\Models\User;

$notifs = UserNotification::orderBy('id', 'desc')->take(20)->get();

foreach ($notifs as $n) {
    $email = User::find($n->user_id)->email ?? 'Unknown';
    echo "ID: {$n->id} | UserID: {$n->user_id} | Email: {$email} | Title: {$n->title}\n";
}
