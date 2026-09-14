<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditUserAction
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() && ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            AuditLog::create([
                'user_id' => $request->user()->id,
                'event' => $this->event($request),
                'category' => in_array($response->getStatusCode(), [401, 403, 429], true) ? 'alerta' : 'alteracao',
                'route' => $request->route()?->getName(),
                'method' => $request->method(),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'metadata' => [
                    'status' => $response->getStatusCode(),
                    'resource_id' => $this->resourceId($request),
                ],
            ]);
        }

        return $response;
    }

    private function event(Request $request): string
    {
        return match (true) {
            $request->isMethod('POST') => 'created',
            $request->isMethod('DELETE') => 'deleted',
            default => 'updated',
        };
    }

    private function resourceId(Request $request): int|string|null
    {
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if (is_object($parameter) && method_exists($parameter, 'getKey')) {
                return $parameter->getKey();
            }
        }

        return null;
    }
}
