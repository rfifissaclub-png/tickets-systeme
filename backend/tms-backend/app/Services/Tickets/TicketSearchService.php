<?php

namespace App\Services\Tickets;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Backs GET /tickets: ownership scoping (see TicketController::index()),
 * advanced filters, full-text search, sorting, and pagination all live here
 * rather than in the controller — this is genuinely business logic (who's
 * allowed to see what, how search ranks results), not request plumbing.
 */
class TicketSearchService
{
    /** @var list<string> */
    private const SORTABLE = ['created_at', 'updated_at', 'priority', 'status'];

    public function search(User $actor, Request $request): LengthAwarePaginator
    {
        $query = Ticket::query()->with(['ticketType', 'client', 'agent']);

        $this->applyOwnershipScope($query, $actor);
        $this->applyFilters($query, $request);
        $searching = $this->applySearch($query, $request);
        $this->applySorting($query, $request, $searching);

        return $query->paginate(min((int) $request->query('per_page', 25), 100));
    }

    private function applyOwnershipScope(Builder $query, User $actor): void
    {
        // tickets.viewAny controls scope, not endpoint access (see
        // TicketPolicy::viewAny): without it, a caller only ever sees
        // tickets they own as client or are assigned as agent.
        if (! $actor->can('tickets.viewAny')) {
            $query->where(function (Builder $q) use ($actor) {
                $q->where('client_id', $actor->id)->orWhere('agent_id', $actor->id);
            });
        }
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        if ($status = $request->query('status')) {
            $query->whereIn('status', $this->csvToArray($status));
        }

        if ($priority = $request->query('priority')) {
            $query->whereIn('priority', $this->csvToArray($priority));
        }

        if ($agentId = $request->query('agent_id')) {
            $query->where('agent_id', $agentId);
        }

        if ($clientId = $request->query('client_id')) {
            $query->where('client_id', $clientId);
        }

        if ($ticketTypeId = $request->query('ticket_type_id')) {
            $query->where('ticket_type_id', $ticketTypeId);
        }

        if ($request->boolean('unassigned')) {
            $query->whereNull('agent_id');
        }

        if ($createdFrom = $request->query('created_from')) {
            $query->whereDate('created_at', '>=', $createdFrom);
        }

        if ($createdTo = $request->query('created_to')) {
            $query->whereDate('created_at', '<=', $createdTo);
        }
    }

    /**
     * Postgres full-text search over the tickets.search_vector generated
     * column (subject weighted above description — see the
     * add_search_vector_to_tickets_table migration). websearch_to_tsquery
     * parses a user-typed query (quotes, AND/OR, "-exclude") forgivingly,
     * unlike plainto_tsquery/to_tsquery which either ignore that syntax or
     * throw on malformed input.
     *
     * @return bool whether a search term was applied (callers use this to
     *              default sorting to relevance instead of recency)
     */
    private function applySearch(Builder $query, Request $request): bool
    {
        $search = trim((string) $request->query('search', ''));

        if ($search === '') {
            return false;
        }

        $query->whereRaw("search_vector @@ websearch_to_tsquery('english', ?)", [$search])
            ->selectRaw("tickets.*, ts_rank(search_vector, websearch_to_tsquery('english', ?)) as search_rank", [$search]);

        return true;
    }

    private function applySorting(Builder $query, Request $request, bool $searching): void
    {
        $sort = $request->query('sort');

        if ($sort === null && $searching) {
            $query->orderByDesc('search_rank');

            return;
        }

        $sort = (string) ($sort ?? '-created_at');
        $column = ltrim($sort, '-');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        $query->orderBy(in_array($column, self::SORTABLE, true) ? $column : 'created_at', $direction);
    }

    /**
     * @return list<string>
     */
    private function csvToArray(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}
