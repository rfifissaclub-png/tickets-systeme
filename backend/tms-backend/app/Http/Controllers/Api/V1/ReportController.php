<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Services\Reports\TicketReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function __construct(private readonly TicketReportService $reportService) {}

    public function overview(Request $request): JsonResponse
    {
        $this->authorizeReportAccess($request);

        return ApiResponse::success($this->reportService->overview());
    }

    public function byAgent(Request $request): JsonResponse
    {
        $this->authorizeReportAccess($request);

        return ApiResponse::success($this->reportService->byAgent());
    }

    /**
     * Platform-wide, cross-tenant — deliberately not gated by a permission
     * string like the others: this is Super Admin only, full stop, checked
     * directly rather than through a permission a Tenant Admin could ever
     * be granted.
     */
    public function byTenant(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->hasRole(RoleName::SuperAdmin->value),
            403,
            'Only a Super Admin may view the platform-wide report.',
        );

        return ApiResponse::success($this->reportService->byTenant());
    }

    public function trends(Request $request): JsonResponse
    {
        $this->authorizeReportAccess($request);

        $validated = $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
        ]);

        $to = isset($validated['to']) ? Carbon::parse($validated['to']) : now();
        $from = isset($validated['from']) ? Carbon::parse($validated['from']) : $to->copy()->subDays(29);

        if ($from->gt($to)) {
            throw ValidationException::withMessages(['from' => ['The from date must be before the to date.']]);
        }

        return ApiResponse::success($this->reportService->trends($from, $to));
    }

    private function authorizeReportAccess(Request $request): void
    {
        abort_unless($request->user()->can('reports.view'), 403);
    }
}
