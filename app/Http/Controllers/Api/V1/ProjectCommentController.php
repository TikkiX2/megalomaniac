<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreProjectCommentRequest;
use App\Http\Resources\ProjectCommentResource;
use App\Models\Project;
use App\Models\ProjectComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectCommentController extends Controller
{
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        abort_if($project->user_id !== $request->user()->id, 403);

        $comments = ProjectComment::where('project_id', $project->id)
            ->with('user')
            ->oldest()
            ->paginate(20);

        return ProjectCommentResource::collection($comments);
    }

    public function store(StoreProjectCommentRequest $request, Project $project): JsonResponse
    {
        abort_if($project->user_id !== $request->user()->id, 403);

        $comment = ProjectComment::create([
            ...$request->validated(),
            'project_id' => $project->id,
            'user_id' => $request->user()->id,
        ]);

        return (new ProjectCommentResource($comment->load('user')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(StoreProjectCommentRequest $request, Project $project, ProjectComment $comment): JsonResponse
    {
        abort_if($project->user_id !== $request->user()->id, 403);
        abort_if($comment->user_id !== $request->user()->id, 403);

        $comment->update($request->validated());

        return new ProjectCommentResource($comment->fresh('user'));
    }

    public function destroy(Request $request, Project $project, ProjectComment $comment): JsonResponse
    {
        abort_if($project->user_id !== $request->user()->id, 403);
        abort_if($comment->user_id !== $request->user()->id, 403);

        $comment->delete();

        return response()->json(['message' => 'Comment deleted.']);
    }
}
