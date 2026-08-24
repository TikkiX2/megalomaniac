<?php

namespace App\Http\Controllers\Freelance;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Quote;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FreelanceDashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $stats = [
            'active_projects' => Project::where('user_id', $user->id)->active()->count(),
            'completed_projects' => Project::where('user_id', $user->id)->completed()->count(),
            'pending_quotes' => Quote::where('user_id', $user->id)->where('status', 'sent')->count(),
            'monthly_income' => Project::where('user_id', $user->id)->sum('paid_amount'), // Temporary fallback or actual monthly logic
            'total_income' => Project::where('user_id', $user->id)->sum('paid_amount'),
            'pending_tasks' => ProjectTask::whereHas('project', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })->whereNotIn('status', ['Done', 'Completed'])->count(),
        ];

        return Inertia::render('freelance/Dashboard', [
            'stats' => $stats,
            'recent_projects' => Project::where('user_id', $user->id)
                ->with('client')
                ->latest()
                ->take(5)
                ->get(),
            'upcoming_tasks' => ProjectTask::whereHas('project', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
                ->where('status', '!=', 'Done')
                ->where('status', '!=', 'Completed')
                ->orderBy('due_date')
                ->take(5)
                ->get(),
        ]);
    }
}
