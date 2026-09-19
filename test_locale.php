<?php
require 'vendor/autoload.php';
\ = require_once 'bootstrap/app.php';
\->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap();
foreach (['id', 'en', 'ar', 'de', 'es', 'fr'] as \) {
    app()->setLocale(\);
    echo \ . ': ' . __('Sudah memiliki akun?') . ' [' . __('Masuk') . '] | ' . __('Belum memiliki akun?') . ' [' . __('Daftar') . ']' . PHP_EOL;
}
