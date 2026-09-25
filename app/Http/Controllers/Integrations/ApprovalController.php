<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integrations\DecideApprovalRequest;
use App\Integrations\Enums\ApprovalStatus;
use App\Integrations\IntegrationExecutor;
use App\Models\ApprovalRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $pending = ApprovalRequest::query()
            ->forUser($user)
            ->pending()
            ->with('connection')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest()
            ->get()
            ->map(fn (ApprovalRequest $approval): array => $this->toArray($approval))
            ->values();

        $history = ApprovalRequest::query()
            ->forUser($user)
            ->where('status', '!=', ApprovalStatus::Pending)
            ->with('connection')
            ->latest('decided_at')
            ->limit(50)
            ->get()
            ->map(fn (ApprovalRequest $approval): array => $this->toArray($approval))
            ->values();

        return Inertia::render('integrations/approvals', [
            'pending' => $pending,
            'history' => $history,
        ]);
    }

    public function approve(DecideApprovalRequest $request, int $approval, IntegrationExecutor $executor): RedirectResponse
    {
        $model = $this->owned($request, $approval);

        abort_unless($model->status === ApprovalStatus::Pending, 422, 'La aprobación ya fue decidida.');

        $executor->approve($model, $request->user(), $request->validated('note'));

        return back()->with('success', 'Acción aprobada y en ejecución.');
    }

    public function reject(DecideApprovalRequest $request, int $approval, IntegrationExecutor $executor): RedirectResponse
    {
        $model = $this->owned($request, $approval);

        abort_unless($model->status === ApprovalStatus::Pending, 422, 'La aprobación ya fue decidida.');

        $executor->reject($model, $request->user(), $request->validated('note'));

        return back()->with('success', 'Acción rechazada.');
    }

    protected function owned(Request $request, int $approvalId): ApprovalRequest
    {
        return ApprovalRequest::query()
            ->forUser($request->user())
            ->findOrFail($approvalId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function toArray(ApprovalRequest $approval): array
    {
        return [
            'id' => $approval->id,
            'connection_id' => $approval->connection_id,
            'connection_name' => $approval->connection?->name,
            'connection_kind' => $approval->connection?->kind,
            'action_key' => $approval->action_key,
            'access' => $approval->access->value,
            'summary' => $approval->summary,
            'rationale' => $approval->rationale,
            'params' => $approval->params,
            'status' => $approval->status->value,
            'decision_note' => $approval->decision_note,
            'expires_at' => $approval->expires_at?->toIso8601String(),
            'decided_at' => $approval->decided_at?->toIso8601String(),
            'created_at' => $approval->created_at?->toIso8601String(),
        ];
    }
}
