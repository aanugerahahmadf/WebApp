<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

print_r(DB::select('SHOW CREATE TABLE fm_messages'));
print_r(DB::select('SHOW CREATE TABLE user_vouchers'));
print_r(DB::select('SHOW CREATE TABLE orders'));
print_r(DB::select('SHOW CREATE TABLE notifications'));
