<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Client::query()
            ->where('user_id', $request->user()->id)
            ->withCount('projects');

        if ($request->has('search')) {
            $query->where('name', 'like', '%'.$request->get('search').'%');
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $clients = $query->latest()->paginate(20);

        return ClientResource::collection($clients);
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = Client::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return (new ClientResource($client))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Client $client, Request $request): ClientResource
    {
        abort_if($client->user_id !== $request->user()->id, 403);

        return new ClientResource($client->loadCount('projects'));
    }

    public function update(StoreClientRequest $request, Client $client): JsonResponse
    {
        abort_if($client->user_id !== $request->user()->id, 403);

        $client->update($request->validated());

        return new ClientResource($client->fresh('projects'));
    }

    public function destroy(Client $client, Request $request): JsonResponse
    {
        abort_if($client->user_id !== $request->user()->id, 403);

        $client->delete();

        return response()->json(['message' => 'Client deleted.']);
    }
}
