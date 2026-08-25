<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Models\TaskSavedView;
use Illuminate\Http\Request;

class TaskSavedViewController extends Controller
{
    public function index(Request $request)
    {
        $views = TaskSavedView::where('user_id', $request->user()->id)->orderBy('name')->get();

        return response()->json($views);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'view_type' => ['required', 'in:table,kanban,calendar,list,gallery,timeline'],
            'filters' => ['nullable', 'array'],
            'sort' => ['nullable', 'array'],
            'group_by' => ['nullable', 'string', 'max:50'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $validated['user_id'] = $request->user()->id;

        if (! empty($validated['is_default'])) {
            TaskSavedView::where('user_id', $request->user()->id)->update(['is_default' => false]);
        }

        TaskSavedView::create($validated);

        return back()->with('success', 'Vista guardada.');
    }

    public function destroy(TaskSavedView $savedView)
    {
        abort_if($savedView->user_id !== auth()->id(), 403);

        $savedView->delete();

        return back()->with('success', 'Vista eliminada.');
    }
}
