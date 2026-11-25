<?php

use App\Http\Middleware\ApplyPlatformSettings;
use App\Http\Middleware\EnsureAccountUsable;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureAllowedHost;
use App\Http\Middleware\EnsurePlatformPermission;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackLastSeen;
use App\Http\Responses\ApiError;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        $middleware->preventRequestsDuringMaintenance(except: ['/up']);
        $middleware->validateCsrfTokens(except: ['stripe/webhook', 'paypal/webhook']);
        $middleware->api(prepend: [ApplyPlatformSettings::class]);

        $middleware->web(
            prepend: [EnsureAllowedHost::class, ApplyPlatformSettings::class],
            append: [
                SetLocale::class,
                HandleAppearance::class,
                HandleInertiaRequests::class,
                EnsureAccountUsable::class,
                TrackLastSeen::class,
                AddLinkHeadersForPreloadedAssets::class,
            ],
        );

        $middleware->alias([
            'active-account' => EnsureActiveAccount::class,
            'platform' => EnsurePlatformPermission::class,
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);

        $middleware->prependToPriorityList(AuthenticatesRequests::class, EnsureAllowedHost::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($exception instanceof ValidationException) {
                return ApiError::response('validation_failed', 'The request is invalid.', 422, $exception->errors());
            }
            if ($exception instanceof AuthenticationException) {
                return ApiError::response('unauthenticated', 'Authentication is required.', 401);
            }
            if ($exception instanceof AuthorizationException) {
                return ApiError::response('forbidden', 'This action is not allowed.', 403);
            }
            if ($exception instanceof ModelNotFoundException) {
                return ApiError::response('not_found', 'The requested resource was not found.', 404);
            }
            if ($exception instanceof HttpExceptionInterface) {
                $status = $exception->getStatusCode();
                $code = match ($status) {
                    401 => 'unauthenticated',
                    403 => 'forbidden',
                    404 => 'not_found',
                    409 => 'conflict',
                    422 => 'validation_failed',
                    429 => 'rate_limited',
                    default => 'http_error',
                };
                $message = match ($status) {
                    401 => 'Authentication is required.',
                    403 => 'This action is not allowed.',
                    404 => 'The requested resource was not found.',
                    409 => 'The request conflicts with current state.',
                    422 => 'The request is invalid.',
                    429 => 'The API rate limit was exceeded.',
                    default => 'The API request could not be completed.',
                };

                return ApiError::response($code, $message, $status);
            }

            return null;
        });
    })->create();
