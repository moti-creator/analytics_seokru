<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Connection;

/**
 * If user has no active session but carries a "remember_connection" cookie,
 * restore the session. Lets users skip re-auth across browser restarts.
 */
class RestoreConnection
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->session()->get('connection_id')) {
            $id = $request->cookie('remember_connection');
            if ($id && Connection::find($id)) {
                $request->session()->put('connection_id', $id);
            }
        }
        return $next($request);
    }
}
