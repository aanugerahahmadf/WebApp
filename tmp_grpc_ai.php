<?php

// Uji: Laravel -> AI Core lewat gRPC (port 50052), memakai stub yang sama
// dengan yang dipakai CBIRService saat AI_CORE_GRPC_ENABLED=true.

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$host = config('services.ai_core_grpc_host');
echo 'config ai_core_grpc_enabled = '.var_export(config('services.ai_core_grpc_enabled'), true).PHP_EOL;
echo 'config ai_core_grpc_host     = '.$host.PHP_EOL;
echo 'config ai_core_url           = '.config('services.ai_core_url').PHP_EOL;
echo PHP_EOL;

[$ip, $port] = explode(':', $host);
$port = (int) $port;

$stub = new \App\Grpc\AiCore\CbirServiceClient($ip.':'.$port, [
    'credentials' => \Grpc\ChannelCredentials::createInsecure(),
]);

echo '=== gRPC CbirService/GetStats ==='.PHP_EOL;

try {
    $request = new \App\Grpc\AiCore\CbirStatsRequest();
    $call = $stub->GetStats($request, [], ['timeout' => 60000]);

    [$response, $status] = $call->wait();

    echo 'status: code='.$status->code.' details='.($status->details ?: '-').PHP_EOL;

    if ($response !== null) {
        echo 'response class: '.get_class($response).PHP_EOL;

        if (method_exists($response, 'serializeToJsonString')) {
            echo 'response: '.$response->serializeToJsonString().PHP_EOL;
        }
    }
} catch (\Throwable $e) {
    echo 'EXCEPTION '.get_class($e).': '.$e->getMessage().PHP_EOL;
}
