<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\UserActionLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogUserAction
{
    /**
     * Handle an incoming request and log administrative actions.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // We only log state-changing operations for authenticated users
        if ($request->user() && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            
            // Derive a user-friendly action name from the route name or path
            $routeName = $request->route()?->getName() ?? $request->path();
            $action = strtoupper($request->method()) . ': ' . $routeName;

            // Strip sensitive fields from request payload
            $payload = $request->except(['password', 'password_confirmation', 'token', 'captcha_value']);

            UserActionLog::create([
                'user_id' => $request->user()->id,
                'action' => $action,
                'ip_address' => $request->ip(),
                'payload' => $payload,
            ]);
        }

        return $response;
    }
}
