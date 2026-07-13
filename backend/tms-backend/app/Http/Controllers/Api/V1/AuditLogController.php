<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Services\Audit\AuditLogQueryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __construct(private readonly AuditLogQueryService $queryService) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('audit_logs.view'), 403);

        $logs = $this->queryService->paginate(
            $request->user(),
            $request->only(['tenant_id', 'action', 'actor_id', 'from', 'to']),
            min((int) $request->query('per_page', 25), 100),
        );

        return ApiResponse::success(
            data: AuditLogResource::collection($logs->items()),
            meta: [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
        );
    }
}
