<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLog\IndexAuditLogRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AuditLogController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AuditLog::class); // AuditLogPolicy: escritura denegada; lectura ROL-02+

        // AuditLog usa BelongsToBusiness -> BusinessScope aísla el tenant automáticamente.
        $logs = AuditLog::query()
            ->latest('created_at')
            ->paginate($request->integer('per_page', 25));

        return AuditLogResource::collection($logs);
    }
}
