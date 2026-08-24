<?php

namespace App\Http\Controllers\Freelance;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectComment;
use Illuminate\Http\Request;

class ProjectCommentController extends Controller
{
    public function index(Project $project)
    {
        return $project->comments()
            ->with(['user', 'replies.user'])
            ->whereNull('parent_id')
            ->latest()
            ->get();
    }

    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'content' => 'required|array', // Yoopta JSON
            'parent_id' => 'nullable|exists:project_comments,id',
        ]);

        $project->comments()->create([
            'user_id' => $request->user()->id,
            'content' => $validated['content'],
            'parent_id' => $validated['parent_id'] ?? null,
        ]);

        return back()->with('success', 'Comentario agregado.');
    }

    public function update(Request $request, ProjectComment $comment)
    {
        $this->authorize('update', $comment); // Need policy, or check ownership here

        if ($request->user()->id !== $comment->user_id) {
            abort(403);
        }

        $validated = $request->validate([
            'content' => 'required|array',
        ]);

        $comment->update($validated);

        return back()->with('success', 'Comentario actualizado.');
    }

    public function destroy(ProjectComment $comment)
    {
        // $this->authorize('delete', $comment);
        if (request()->user()->id !== $comment->user_id) {
            abort(403);
        }

        $comment->delete();

        return back()->with('success', 'Comentario eliminado.');
    }
}
