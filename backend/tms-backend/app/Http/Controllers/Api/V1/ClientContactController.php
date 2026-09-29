<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\StoreClientContactRequest;
use App\Http\Requests\Clients\UpdateClientContactRequest;
use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientContactController extends Controller
{
    public function index(Request $request, Client $client): JsonResponse
    {
        $this->authorize('view', $client);

        $contacts = $client->contacts()->where('tenant_id', $request->user()->tenant_id)->paginate(25);

        return response()->json(['data' => $contacts]);
    }

    public function store(StoreClientContactRequest $request, Client $client): JsonResponse
    {
        // Creating a contact for a client is allowed to any authenticated
        // tenant user who can view the client (not only those with
        // tenants.update). Use the `view` ability which checks tenant
        // membership rather than the tenants.update permission.
        $this->authorize('view', $client);

        $data = $request->validated();
        $data['tenant_id'] = $request->user()->tenant_id;
        $data['client_id'] = $client->id;

        $contact = ClientContact::create($data);

        return response()->json(['data' => $contact], 201);
    }

    public function update(UpdateClientContactRequest $request, Client $client, ClientContact $contact): JsonResponse
    {
        $this->authorize('update', $client);

        // Ensure the contact belongs to the client and tenant to avoid IDOR.
        if ($contact->client_id !== $client->id || $contact->tenant_id !== $request->user()->tenant_id) {
            abort(404);
        }

        $validated = $request->validated();
        // Prevent changing ownership fields even if provided.
        unset($validated['client_id'], $validated['tenant_id']);

        $contact->update($validated);

        return response()->json(['data' => $contact]);
    }

    public function destroy(Request $request, Client $client, ClientContact $contact): JsonResponse
    {
        $this->authorize('update', $client);

        if ($contact->client_id !== $client->id || $contact->tenant_id !== $request->user()->tenant_id) {
            abort(404);
        }

        $contact->delete();

        return response()->json('', 204);
    }
}
