<?php

namespace App\Http\Controllers\Today;

use App\Http\Controllers\Controller;
use App\Models\ProjectTask;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ArchiveController extends Controller
{
    public function index(Request $request)
    {
        $items = ProjectTask::where('user_id', $request->user()->id)
            ->archived()
            ->orderByDesc('archived_at')
            ->limit(100)
            ->get(['id', 'title', 'archived_at']);

        return Inertia::render('today/Archivadas', ['items' => $items]);
    }

    public function restore(Request $request)
    {
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);
        ProjectTask::where('user_id', $request->user()->id)->whereIn('id', $data['ids'])->update(['archived_at' => null]);

        return back()->with('success', 'Restauradas.');
    }
}
