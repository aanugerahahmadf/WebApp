<?php declare(strict_types=1);

use Spiral\RoadRunner\GRPC\Invoker;
use Spiral\RoadRunner\GRPC\Server;
use Spiral\RoadRunner\GRPC\ServiceInterface;
use Spiral\RoadRunner\Worker;

require __DIR__ . "/vendor/autoload.php";

/** @var \Illuminate\Foundation\Application $app */
$app = require_once __DIR__ . "/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$server = new Server(new Invoker(), [
    // Never let RoadRunner expose gRPC exception details in production.
    'debug' => (bool) config('app.debug', false),
]);
$server->registerService(
    \App\Grpc\User\V1\AuthService::class,
    $app->make(\App\Grpc\User\V1\AuthService::class),
);

// These services already expose the RoadRunner signature directly and use
// generated wedding.user.v1 protobuf messages for both input and output.
foreach ([
    \App\Grpc\User\V1\CatalogService::class,
    \App\Grpc\User\V1\CategoryService::class,
    \App\Grpc\User\V1\PackageService::class,
    \App\Grpc\User\V1\ProfileService::class,
    \App\Grpc\User\V1\OrderService::class,
    \App\Grpc\User\V1\CbirService::class,
] as $serviceClass) {
    $service = $app->make($serviceClass);
    $server->registerService($serviceClass, $service);
}

$worker = Worker::create();
$server->serve($worker);
