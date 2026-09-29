<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\StoreClientRequest;
use App\Http\Requests\Clients\UpdateClientRequest;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $perPage = (int) $request->query('per_page', 25);
        $q = $request->query('q');

        $builder = Client::query();

        // Filtering by linked/direct clients. If is_linked is provided, filter accordingly.
        if ($request->has('is_linked')) {
            $isLinked = filter_var($request->query('is_linked'), FILTER_VALIDATE_BOOLEAN);
            if ($isLinked) {
                // Linked clients for this tenant are those with parent_tenant_id == current tenant
                $builder->where('is_linked', true)->where('parent_tenant_id', $tenantId);
            } else {
                // Direct clients: tenant-owned and not marked as linked
                $builder->where('tenant_id', $tenantId)->where(function ($q) {
                    $q->whereNull('is_linked')->orWhere('is_linked', false);
                });
            }
        } else {
            // Default: clients for the current tenant
            $builder->where('tenant_id', $tenantId);
        }

        if ($q) {
            $builder->where(function ($qb) use ($q) {
                $qb->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%");
            });
        }

        $clients = $builder->paginate($perPage);

        return response()->json(['data' => $clients]);
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = $request->user()->tenant_id;

        $client = Client::create($data);

        return response()->json(['data' => $client], 201);
    }

    public function show(Request $request, Client $client): JsonResponse
    {
        $this->authorize('view', $client);

        return response()->json(['data' => $client]);
    }

    public function update(UpdateClientRequest $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $client->update($request->validated());

        return response()->json(['data' => $client]);
    }

    public function destroy(Request $request, Client $client): JsonResponse
    {
        $this->authorize('delete', $client);

        $client->delete();

        return response()->json('', 204);
    }
}
