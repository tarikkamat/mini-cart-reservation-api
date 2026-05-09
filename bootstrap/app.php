<?php

declare(strict_types=1);

use App\Domain\Cart\Exceptions\MixedCurrencyException;
use App\Domain\Cart\Exceptions\ProductInactiveException;
use App\Domain\Cart\Exceptions\ProductNotFoundException;
use App\Domain\Reservation\Exceptions\IdempotencyConflictException;
use App\Domain\Reservation\Exceptions\InsufficientStockException;
use App\Http\Middleware\IdempotencyMiddleware;
use App\Http\Middleware\RequestIdMiddleware;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'idempotency' => IdempotencyMiddleware::class,
        ]);

        $middleware->appendToGroup('api', RequestIdMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (InsufficientStockException $e) {
            return response()->json([
                'error' => [
                    'code' => 'INSUFFICIENT_STOCK',
                    'message' => 'Requested quantity exceeds available stock.',
                    'details' => [
                        [
                            'product_id' => $e->productId,
                            'requested' => $e->requestedQuantity,
                            'available' => $e->availableQuantity,
                        ],
                    ],
                ],
            ], 409);
        });

        $exceptions->render(function (ProductInactiveException $e) {
            return response()->json([
                'error' => [
                    'code' => 'PRODUCT_INACTIVE',
                    'message' => 'Product is not active.',
                    'details' => [['product_id' => $e->productId]],
                ],
            ], 422);
        });

        $exceptions->render(function (ProductNotFoundException $e) {
            return response()->json([
                'error' => [
                    'code' => 'PRODUCT_NOT_FOUND',
                    'message' => 'Product was not found.',
                    'details' => [['product_id' => $e->productId]],
                ],
            ], 422);
        });

        $exceptions->render(function (MixedCurrencyException $e) {
            return response()->json([
                'error' => [
                    'code' => 'MIXED_CURRENCY',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        });

        $exceptions->render(function (IdempotencyConflictException $e) {
            return response()->json([
                'error' => [
                    'code' => 'IDEMPOTENCY_CONFLICT',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'The given data was invalid.',
                    'details' => $e->errors(),
                ],
            ], 422);
        });

        $notFound = fn (Request $request) => $request->is('api/*')
            ? response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Resource not found.',
                ],
            ], 404)
            : null;

        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $notFound($request));
        $exceptions->render(fn (ModelNotFoundException $e, Request $request) => $notFound($request));
    })->create();
