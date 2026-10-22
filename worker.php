<?php declare(strict_types=1);

use Spiral\RoadRunner\GRPC\Invoker;
use Spiral\RoadRunner\GRPC\Server;
use Spiral\RoadRunner\GRPC\ServiceInterface;
use Spiral\RoadRunner\Worker;

require __DIR__ . "/vendor/autoload.php";

/** @var \Illuminate\Foundation\Application $app */
$app = require_once __DIR__ . "/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

interface TestServiceInterface extends ServiceInterface {
    public const NAME = "wedding.user.v1.TestService";
}

class TestService implements TestServiceInterface {
    public function Ping(\Spiral\RoadRunner\GRPC\ContextInterface $ctx, \App\Grpc\User\V1\MeRequest $input): \App\Grpc\User\V1\MeRequest {
        return new \App\Grpc\User\V1\MeRequest();
    }
}

$server = new Server(new Invoker(), ["debug" => true]);
$server->registerService(TestServiceInterface::class, new TestService());

$worker = Worker::create();
$server->serve($worker);
