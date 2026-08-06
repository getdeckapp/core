<?php

namespace Deck\Core\Http\Middleware;

use Closure;
use Deck\Core\Dispatch\DispatchGroup;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AssignDispatchGroup
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        DispatchGroup::ensureRequestGroup($request);

        return $next($request);
    }
}
