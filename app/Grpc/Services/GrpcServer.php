<?php

namespace App\Grpc\Services;

use Grpc\Server;
use Grpc\ServerCall;
use Grpc\Status;
use Google\Protobuf\Internal\GPBEmpty;

class GrpcServer
{
    protected Server $server;

    public function __construct()
    {
        $this->server = new Server();
    }

    public function registerServices()
    {
        // Register all gRPC services
        $this->server->start();
    }

    public function run()
    {
        $this->registerServices();
        $this->server->handle();
    }

    protected function authenticate(string $metadata): ?\App\Models\User\User
    {
        $token = $metadata['authorization'][0] ?? null;

        if (!$token) {
            return null;
        }

        // Remove 'Bearer ' prefix if present
        $token = str_replace('Bearer ', '', $token);

        // Sanctum::findToken() hashes and resolves the plain-text token.
        // Do not call Hash::check() here: it returns a boolean, which used to
        // replace the token before Sanctum received it and made every gRPC
        // authenticated request fail (or accidentally query token "1").
        $sanctumToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);

        if (!$sanctumToken) {
            return null;
        }

        $user = $sanctumToken->tokenable;

        return $user instanceof \App\Models\User\User ? $user : null;
    }

    protected function errorResponse(ServerCall $call, string $message, int $code = Status::UNAUTHENTICATED)
    {
        $call->sendResponse(new GPBEmpty());
        $call->sendStatusFromCode($code, $message);
    }
}
