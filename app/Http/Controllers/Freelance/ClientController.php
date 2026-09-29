<?php

namespace App\Http\Controllers\Freelance;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $clients = Client::query()
            ->where('user_id', $request->user()->id)
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('company', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('freelance/clients/Index', [
            'clients' => $clients,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('freelance/clients/Form', [
            'people' => Person::where('user_id', request()->user()->id)
                ->visible()
                ->get(['id', 'first_name', 'last_name']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'person_id' => [
                'nullable',
                Rule::exists('people', 'id')->where('user_id', $request->user()->id),
            ],
        ]);

        $request->user()->clients()->create($validated);

        return redirect()->route('freelance.clients.index')
            ->with('success', 'Cliente creado exitosamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Client $client)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Client $client)
    {
        return Inertia::render('freelance/clients/Form', [
            'client' => $client,
            'people' => Person::where('user_id', request()->user()->id)
                ->visible()
                ->get(['id', 'first_name', 'last_name']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
            'person_id' => [
                'nullable',
                Rule::exists('people', 'id')->where('user_id', $request->user()->id),
            ],
        ]);

        $client->update($validated);

        return redirect()->route('freelance.clients.index')
            ->with('success', 'Cliente actualizado exitosamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Client $client)
    {
        $client->delete();

        return redirect()->route('freelance.clients.index')
            ->with('success', 'Cliente eliminado exitosamente.');
    }
}
