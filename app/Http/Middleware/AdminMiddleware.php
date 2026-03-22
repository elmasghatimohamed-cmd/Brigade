<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response|Closure
    {
        $user = $request->user();
        
        if (!$user || $user->role !== 'admin_restaurant') {
            return response()->json([
                'message' => 'Accès non autorisé. Role administrateur requis.'
            ], 403);
        }

        return $next($request);
    }
}
