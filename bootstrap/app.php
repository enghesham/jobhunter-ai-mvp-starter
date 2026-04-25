<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (\Throwable $exception): void {
            logger()->error('Unhandled application exception.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        });

        $exceptions->render(function (\Throwable $exception, $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return match (true) {
                $exception instanceof ValidationException => response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $exception->errors(),
                ], 422),
                $exception instanceof AuthenticationException => response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401),
                $exception instanceof AuthorizationException, $exception instanceof AccessDeniedHttpException => response()->json([
                    'success' => false,
                    'message' => 'This action is unauthorized.',
                ], 403),
                $exception instanceof ModelNotFoundException, $exception instanceof NotFoundHttpException => response()->json([
                    'success' => false,
                    'message' => 'Resource not found.',
                ], 404),
                default => response()->json([
                    'success' => false,
                    'message' => config('app.debug') ? $exception->getMessage() : 'Server error.',
                ], 500),
            };
        });
    })->create();
