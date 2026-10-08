<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\SubscriptionRequiredException;
use App\Http\Controllers\Web\SubscriptionPageController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** HTML adapter around the existing gate; never authorizes, changes it or handles API rejections. */
final class PresentSubscriptionRestriction
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
            // Laravel's routing pipeline may render the exception before returning to this middleware.
            if (!$request->expectsJson() && $response instanceof \Illuminate\Http\JsonResponse
                && $response->getStatusCode() === 403 && ($response->getData(true)['code'] ?? null) === 'SUBSCRIPTION_REQUIRED') {
                return response(app(SubscriptionPageController::class)->restricted($request), 403);
            }
            return $response;
        }
        catch (SubscriptionRequiredException $error) {
            if ($request->expectsJson()) throw $error;
            return response(app(SubscriptionPageController::class)->restricted($request), 403);
        }
    }
}
