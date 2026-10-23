<?php

use App\Exceptions\BusinessException;
use App\Http\Middleware\EnsureRole;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1', // contrato §1: base /api/v1
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['role' => EnsureRole::class]);
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // RNF-4: todo error usa { code, message, details }. Nunca trazas ni SQL.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            $error = fn (string $code, string $message, int $status, array $details = []) =>
                response()->json(['code' => $code, 'message' => $message, 'details' => $details], $status);

            if ($e instanceof BusinessException) {
                return $error($e->errorCode, $e->getMessage(), $e->status, $e->details);
            }
            if ($e instanceof ValidationException) {
                $details = [];
                foreach ($e->errors() as $field => $messages) {
                    $details[] = ['field' => $field, 'issue' => $messages[0]];
                }
                return $error('VALIDATION_ERROR', 'Hay campos inválidos.', 422, $details);
            }
            if ($e instanceof AuthenticationException) {
                return $error('UNAUTHENTICATED', 'Se requiere un token válido.', 401);
            }
            if ($e instanceof HttpExceptionInterface) {
                return match ($e->getStatusCode()) {
                    403 => $error('FORBIDDEN_ROLE', 'No tiene permisos para esta operación.', 403),
                    404 => $error('NOT_FOUND', 'El recurso no existe.', 404),
                    default => $error('HTTP_ERROR', 'Solicitud no válida.', $e->getStatusCode()),
                };
            }
            return $error('INTERNAL_ERROR', 'Error interno del servidor.', 500);
        });
    })->create();