<?php

namespace App\Http\Controllers\Today;

use App\Http\Controllers\Controller;
use App\Http\Requests\Today\StoreBlockRequest;
use App\Http\Requests\Today\UpdateBlockRequest;
use App\Models\Block;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BlockController extends Controller
{
    public function store(StoreBlockRequest $request): RedirectResponse
    {
        Block::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'active' => $request->boolean('active', true),
        ]);

        return back()->with('success', 'Bloque creado.');
    }

    public function update(UpdateBlockRequest $request, Block $block): RedirectResponse
    {
        abort_unless($block->user_id === $request->user()->id, 404);
        $block->update($request->validated());

        return back()->with('success', 'Bloque actualizado.');
    }

    public function destroy(Request $request, Block $block): RedirectResponse
    {
        abort_unless($block->user_id === $request->user()->id, 404);
        $block->delete();

        return back()->with('success', 'Bloque eliminado.');
    }
}
