<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\App\Exceptions\ConcurrentConflictException $exception, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                ], 409);
            }
        });

        // API 404s: never leak "No query results for model [App\Models\…] {id}".
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $exception, \Illuminate\Http\Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            $previous = $exception->getPrevious();
            $message = $previous instanceof \Illuminate\Database\Eloquent\ModelNotFoundException
                ? \App\Support\ApiNotFoundMessage::fromModelNotFound($previous)
                : \App\Support\ApiNotFoundMessage::generic();

            return response()->json([
                'message' => $message,
            ], 404);
        });
    })->create();
