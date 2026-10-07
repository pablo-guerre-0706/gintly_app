<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Requests\Api\V1\Billing\CancelSubscriptionRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Web presentation only. Catalog and owner authorization remain Backend collaborators. */
final class SubscriptionPageController extends Controller
{
    public function landing(Request $request): View
    {
        return view('landing', ['billingPlans' => app(BillingController::class)->plans()->resolve($request)]);
    }

    public function index(Request $request): View
    {
        return view('billing.index', ['canManageBilling' => $this->canManage($request), 'billingMode' => 'manage']);
    }

    public function access(Request $request): View
    {
        return view('billing.index', ['canManageBilling' => $this->canManage($request), 'billingMode' => 'access']);
    }

    public function returned(Request $request): View
    {
        return view('billing.index', ['canManageBilling' => $this->canManage($request), 'billingMode' => 'return']);
    }

    public function restricted(Request $request): View
    {
        return view('billing.restricted', ['canManageBilling' => $this->canManage($request)]);
    }

    private function canManage(Request $request): bool
    {
        // Reuse the exact real-owner/active/role authorization, not a second policy.
        $authorization = new CancelSubscriptionRequest();
        $authorization->setUserResolver(fn () => $request->user());
        return $authorization->authorize();
    }
}
