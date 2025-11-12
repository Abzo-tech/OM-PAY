<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
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
        $this->reportable(function (Throwable $e) {
            $request = request();
            $user = auth()->user();

            \Log::error('Exception caught', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request' => [
                    'method' => $request ? $request->method() : 'N/A',
                    'url' => $request ? $request->fullUrl() : 'N/A',
                    'ip' => $request ? $request->ip() : 'N/A',
                    'user_agent' => $request ? $request->userAgent() : 'N/A',
                ],
                'user' => $user ? [
                    'id' => $user->id,
                    'email' => $user->email,
                ] : 'Not authenticated',
                'environment' => app()->environment(),
                'timestamp' => now()->toISOString(),
            ]);
        });
    }
}

