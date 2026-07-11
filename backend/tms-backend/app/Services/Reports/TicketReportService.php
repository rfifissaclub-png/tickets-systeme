<?php

namespace App\Services\Reports;

use App\Models\Ticket;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reports the raw metrics an SLA system would be built on top of (average
 * first response / resolution time) — actual SLA policies, targets, and
 * breach tracking are future-roadmap scope (a real "SLA engine" needs a
 * policy model that doesn't exist yet), not reimplemented here.
 */
class TicketReportService
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $tenantId = $this->tenantContext->requireTenant();

        $statusCounts = Ticket::query()
            ->where('tenant_id', $tenantId)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $priorityCounts = Ticket::query()
            ->where('tenant_id', $tenantId)
            ->selectRaw('priority, count(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority');

        $averages = Ticket::query()
            ->where('tenant_id', $tenantId)
            ->selectRaw('
                AVG(EXTRACT(EPOCH FROM (resolved_at - created_at))) FILTER (WHERE resolved_at IS NOT NULL) as avg_resolution_seconds,
                AVG(EXTRACT(EPOCH FROM (first_response_at - created_at))) FILTER (WHERE first_response_at IS NOT NULL) as avg_first_response_seconds
            ')
            ->first();

        return [
            'total_tickets' => (int) $statusCounts->sum(),
            'by_status' => $statusCounts,
            'by_priority' => $priorityCounts,
            'avg_resolution_seconds' => $averages?->avg_resolution_seconds !== null ? round((float) $averages->avg_resolution_seconds) : null,
            'avg_first_response_seconds' => $averages?->avg_first_response_seconds !== null ? round((float) $averages->avg_first_response_seconds) : null,
        ];
    }

    /**
     * @return Collection<int, object>
     */
    public function byAgent(): Collection
    {
        $tenantId = $this->tenantContext->requireTenant();

        return DB::table('tickets')
            ->join('users', 'users.id', '=', 'tickets.agent_id')
            ->where('tickets.tenant_id', $tenantId)
            ->whereNotNull('tickets.agent_id')
            ->groupBy('tickets.agent_id', 'users.name')
            ->selectRaw('
                tickets.agent_id,
                users.name as agent_name,
                count(*) as ticket_count,
                AVG(EXTRACT(EPOCH FROM (tickets.resolved_at - tickets.created_at))) FILTER (WHERE tickets.resolved_at IS NOT NULL) as avg_resolution_seconds,
                AVG(EXTRACT(EPOCH FROM (tickets.first_response_at - tickets.created_at))) FILTER (WHERE tickets.first_response_at IS NOT NULL) as avg_first_response_seconds
            ')
            ->orderByDesc('ticket_count')
            ->get();
    }

    /**
     * Platform-wide, cross-tenant. Callers MUST verify the actor is a Super
     * Admin before calling this — it always runs inside an audited bypass,
     * which does not itself enforce authorization (see TenantContext::bypass()).
     *
     * @return Collection<int, object>
     */
    public function byTenant(): Collection
    {
        return $this->tenantContext->bypass(
            'platform-wide tickets-by-tenant report',
            fn () => DB::table('tickets')
                ->join('tenants', 'tenants.id', '=', 'tickets.tenant_id')
                ->groupBy('tickets.tenant_id', 'tenants.name')
                ->selectRaw('tickets.tenant_id, tenants.name as tenant_name, count(*) as ticket_count')
                ->orderByDesc('ticket_count')
                ->get(),
        );
    }

    /**
     * Daily ticket creation vs. resolution counts over [$from, $to] — shaped
     * for a frontend line/bar chart (Phase 6), not a rendered chart itself.
     *
     * @return Collection<int, array{date: string, created: int, resolved: int}>
     */
    public function trends(Carbon $from, Carbon $to): Collection
    {
        $tenantId = $this->tenantContext->requireTenant();

        // $to is a calendar-day boundary (e.g. from a plain "2026-02-02"
        // query param, parsed to that day's midnight) — using it as-is for
        // whereBetween's upper bound would exclude every row created later
        // that same day. The day-bucket loop below still stops at $to's
        // calendar date; only the query's own range needs to reach its end.
        $queryTo = $to->copy()->endOfDay();

        $created = Ticket::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $queryTo])
            ->selectRaw('DATE(created_at) as day, count(*) as created_count')
            ->groupBy('day')
            ->pluck('created_count', 'day');

        $resolved = Ticket::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('resolved_at')
            ->whereBetween('resolved_at', [$from, $queryTo])
            ->selectRaw('DATE(resolved_at) as day, count(*) as resolved_count')
            ->groupBy('day')
            ->pluck('resolved_count', 'day');

        $days = collect();

        for ($date = $from->copy()->startOfDay(); $date->lte($to); $date->addDay()) {
            $key = $date->toDateString();

            $days->push([
                'date' => $key,
                'created' => (int) ($created[$key] ?? 0),
                'resolved' => (int) ($resolved[$key] ?? 0),
            ]);
        }

        return $days;
    }
}
