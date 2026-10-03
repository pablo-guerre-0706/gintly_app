<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLog\IndexAuditLogRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AuditLogController extends Controller
{
    use AuthorizesRequests;

    public function index(IndexAuditLogRequest $request): AnonymousResourceCollection
    {
        // viewAny lo evalúa también IndexAuditLogRequest::authorize; se conserva
        // aquí como candado explícito (AuditLogPolicy: escritura denegada; lectura ROL-02+).
        $this->authorize('viewAny', AuditLog::class);

        // AuditLog usa BelongsToBusiness -> BusinessScope aísla el tenant automáticamente.
        // IndexAuditLogRequest valida y sanea filtros, orden y paginación (contrato MOD-01).
        $logs = AuditLog::query()
            ->when(
                $request->validated('user_id'),
                fn ($q, $userId) => $q->where('user_id', $userId)
            )
            ->when(
                $request->validated('action'),
                fn ($q, $action) => $q->where('action', $action)
            )
            ->when(
                $request->validated('auditable_type'),
                fn ($q, $type) => $q->where('auditable_type', $type)
            )
            ->when(
                $request->validated('auditable_id'),
                fn ($q, $id) => $q->where('auditable_id', $id)
            )
            ->when(
                $request->validated('ip_address'),
                fn ($q, $ip) => $q->where('ip_address', $ip)
            )
            // Rango interpretado en el huso del negocio, convertido a UTC.
            ->when(
                $request->fromDateTime(),
                fn ($q, $from) => $q->where('created_at', '>=', $from)
            )
            ->when(
                $request->toDateTime(),
                fn ($q, $to) => $q->where('created_at', '<=', $to)
            )
            ->orderBy($request->sortColumn('created_at'), $request->sortDirection('desc'))
            ->paginate($request->perPage());

        return AuditLogResource::collection($logs);
    }
}
