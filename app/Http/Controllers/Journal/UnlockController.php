<?php

declare(strict_types=1);

namespace App\Http\Controllers\Journal;

use App\Http\Controllers\Controller;
use App\Services\Journal\JournalAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UnlockController extends Controller
{
    public function __construct(
        private readonly JournalAccess $access,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($validated['password'], $request->user()->password)) {
            return back()->withErrors([
                'password' => 'Contraseña incorrecta.',
            ]);
        }

        $this->access->unlock($request);

        return back()->with('success', 'Diario desbloqueado por 15 minutos.');
    }
}
