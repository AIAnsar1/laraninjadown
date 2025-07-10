<?php

namespace App\Http\Middleware;

use App\Dtos\ApiResponse;
use App\Models\User;
use Closure;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Http\Response as ResponseAlias;
use Symfony\Component\HttpFoundation\Response;

class RBAC
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): ResponseAlias|JsonResponse|RedirectResponse
    {
        $action = explode('.', $request->route()->getAction('as'));
        /* @var $user User */
        $user = auth()->user();

        if (empty($user))
        {
            $userActiveRole = 'guest';
        }
        else
        {
            $userActiveRole = !empty($user->getActiveRole()) ? $user->getActiveRole()->role_code : 'guest';
        }
        $rbac = config('rbac.' . $userActiveRole);

        if (is_null($rbac) or !array_key_exists($action[0], $rbac) or !in_array($action[1], $rbac[$action[0]]))
        {
            return ApiResponse::error('NOT ALLOWED', Response::HTTP_METHOD_NOT_ALLOWED);
        }
        return $next($request);
    }
}
