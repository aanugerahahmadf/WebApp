<?php

namespace App\Exceptions\Handler;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e): void {
            //
        });
    }

    /**
     * Get the view used to render HTTP exceptions.
     */
    protected function getHttpExceptionView(HttpExceptionInterface $e)
    {
        $status = $e->getStatusCode();
        $nested = "User.errors.{$status}.{$status}";

        if (view()->exists($nested)) {
            return $nested;
        }

        return parent::getHttpExceptionView($e);
    }
}
