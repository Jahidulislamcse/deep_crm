<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{ContentPost, Client, User, Notification};
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class ContentPostController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = ContentPost::with(['client', 'assignedTo', 'createdBy']);

        // SMM/Staff scoping: if staff has assigned clients or specific post assignments
        if ($user && !$user->isOwner() && !$user->isAdmin()) {
            if ($user->role === 'smm') {
                $clientIds = Client::where('assigned_smm', $user->id)->pluck('id');
                $query->where(function ($q) use ($clientIds, $user) {
                    $q->whereIn('client_id', $clientIds)
                      ->orWhere('assigned_to', $user->id)
                      ->orWhere('created_by', $user->id);
                });
            } else {
                // Creative staff (designer, motion, video, etc.) sees their assigned posts or posts where they are involved
                $query->where(function ($q) use ($user) {
                    $q->where('assigned_to', $user->id)
                      ->orWhere('created_by', $user->id);
                });
            }
        }

        // Filters
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('platform')) {
            $query->where('platform', $request->platform);
        }

        if ($request->filled('post_type')) {
            $query->where('post_type', $request->post_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Month / Year filtering for calendar
        $month = $request->get('month', now()->format('m'));
        $year  = $request->get('year', now()->format('Y'));
        
        $viewMode = $request->get('view', 'list'); // 'list', 'calendar', 'pipeline'

        if ($viewMode === 'calendar') {
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate   = Carbon::createFromDate($year, $month, 1)->endOfMonth();
            $query->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()]);
        }

        $posts = $query->orderBy('date', 'asc')->orderBy('publish_time', 'asc')->get();

        if ($request->wantsJson()) {
            return response()->json($posts);
        }

        // Metrics for summary badges
        $metricsQuery = ContentPost::query();
        if ($request->filled('client_id')) {
            $metricsQuery->where('client_id', $request->client_id);
        }
        $totalCount = (clone $metricsQuery)->count();
        $stageCounts = [];
        foreach (ContentPost::STAGES as $stageKey => $stageMeta) {
            $stageCounts[$stageKey] = (clone $metricsQuery)->where('status', $stageKey)->count();
        }

        $clients = Client::orderBy('name')->get();
        $teamMembers = User::where('active', true)->orderBy('name')->get();

        return view('calendar.index', compact(
            'posts',
            'clients',
            'teamMembers',
            'month',
            'year',
            'viewMode',
            'totalCount',
            'stageCounts'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'             => 'required|string|max:255',
            'client_id'         => 'required|exists:clients,id',
            'assigned_to'       => 'nullable|exists:users,id',
            'platform'          => 'required|string|max:50',
            'post_type'         => 'required|string|max:50',
            'date'              => 'required|date',
            'publish_time'      => 'nullable|string|max:20',
            'status'            => 'required|string|max:50',
            'caption'           => 'nullable|string',
            'creative_url'      => 'nullable|string|max:1000',
            'brief'             => 'nullable|string',
            'notes'             => 'nullable|string',
        ]);

        $validated['created_by'] = Auth::id() ?? 1;

        $post = ContentPost::create($validated);

        // Instant alert if assigned to a creator at creation
        if (!empty($post->assigned_to) && (int) $post->assigned_to !== (int) Auth::id()) {
            $post->load('client');
            $clientName = $post->client->name ?? 'Client';
            $actorName = Auth::user()->name ?? 'SMM Lead';
            $formatLabel = $post->format_info['label'] ?? 'Content';

            Notification::create([
                'user_id'       => $post->assigned_to,
                'icon'          => 'fa-pen-to-square',
                'bg_color'      => 'bg-purple-600',
                'message'       => "New {$formatLabel} Assigned: {$actorName} assigned '{$post->title}' ({$clientName}) to you in stage '" . ($post->stage_info['label'] ?? 'Draft') . "'.",
                'is_admin_only' => false,
            ]);
        }

        return redirect()->back()->with('success', 'Content item added to calendar successfully!');
    }

    public function update(Request $request, ContentPost $post)
    {
        $oldStatus = $post->status;

        $validated = $request->validate([
            'title'             => 'required|string|max:255',
            'client_id'         => 'required|exists:clients,id',
            'assigned_to'       => 'nullable|exists:users,id',
            'platform'          => 'required|string|max:50',
            'post_type'         => 'required|string|max:50',
            'date'              => 'required|date',
            'publish_time'      => 'nullable|string|max:20',
            'status'            => 'required|string|max:50',
            'caption'           => 'nullable|string',
            'creative_url'      => 'nullable|string|max:1000',
            'brief'             => 'nullable|string',
            'notes'             => 'nullable|string',
            'internal_feedback' => 'nullable|string',
            'client_feedback'   => 'nullable|string',
        ]);

        $post->update($validated);

        if ($oldStatus !== $post->status) {
            $this->notifyStageTransition($post, $oldStatus, $post->status);
        }

        return redirect()->back()->with('success', 'Content item updated successfully!');
    }

    public function updateStage(Request $request, ContentPost $post)
    {
        $oldStatus = $post->status;

        $validated = $request->validate([
            'status'            => 'required|string|in:draft,internal_review,client_approval,revision,approved,published',
            'internal_feedback' => 'nullable|string',
            'client_feedback'   => 'nullable|string',
        ]);

        $post->update($validated);

        if ($oldStatus !== $post->status) {
            $this->notifyStageTransition($post, $oldStatus, $post->status);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Stage updated to ' . ($post->stage_info['label'] ?? $post->status),
                'stage'   => $post->status,
            ]);
        }

        return redirect()->back()->with('success', 'Post moved to ' . ($post->stage_info['label'] ?? $post->status) . ' — team notified!');
    }

    /**
     * Dispatch instant stage-transition notifications to the next responsible creators/reviewers
     * in the 6-stage content approval workflow.
     */
    private function notifyStageTransition(ContentPost $post, string $oldStatus, string $newStatus): void
    {
        $post->load(['client', 'assignedTo', 'createdBy']);

        $actorId    = Auth::id();
        $actorName  = Auth::user()->name ?? 'Team Member';
        $clientName = $post->client->name ?? 'Client';
        $stageLabel = $post->stage_info['label'] ?? ucfirst(str_replace('_', ' ', $newStatus));
        $formatLabel = $post->format_info['label'] ?? 'Content';

        $adminIds = User::whereIn('role', ['owner', 'admin'])->where('active', true)->pluck('id')->all();
        $clientSmmId   = $post->client->assigned_smm ?? null;
        $clientSalesId = $post->client->assigned_sales ?? null;

        $recipients = [];
        $icon = 'fa-bell';
        $bgColor = 'bg-indigo-600';
        $actionHint = "Check the content board to take action.";

        switch ($newStatus) {
            case 'draft':
                $recipients = [$post->assigned_to];
                $icon = 'fa-pen';
                $bgColor = 'bg-slate-700';
                $actionHint = "Your turn to work on the initial draft/design!";
                break;

            case 'internal_review':
                // Creator submitted for QA -> Notify SMM Lead & Admins
                $recipients = array_merge([$clientSmmId, $post->created_by], $adminIds);
                $icon = 'fa-magnifying-glass';
                $bgColor = 'bg-indigo-600';
                $actionHint = "Ready for Creative Lead / SMM internal QA review.";
                break;

            case 'client_approval':
                // Passed Internal QA -> Notify Client SMM, Sales Account Rep & Creator
                $recipients = [$clientSmmId, $clientSalesId, $post->created_by, $post->assigned_to];
                $icon = 'fa-paper-plane';
                $bgColor = 'bg-blue-600';
                $actionHint = "Sent forward for client sign-off.";
                break;

            case 'revision':
                // Revision Needed -> Urgent alert to the assigned creator & creator/SMM
                $recipients = [$post->assigned_to, $post->created_by];
                $icon = 'fa-rotate-right';
                $bgColor = 'bg-amber-600';
                $feedback = trim($post->client_feedback ?: ($post->internal_feedback ?? ''));
                $actionHint = $feedback
                    ? "Your turn to revise! Feedback: \"" . \Illuminate\Support\Str::limit($feedback, 90) . "\""
                    : "Your turn to revise — please check review feedback on the board!";
                break;

            case 'approved':
                // Approved -> Notify SMM (to schedule/publish) & Creator
                $recipients = [$clientSmmId, $post->assigned_to, $post->created_by];
                $icon = 'fa-circle-check';
                $bgColor = 'bg-emerald-600';
                $actionHint = "Approved and cleared for final scheduling & publishing!";
                break;

            case 'published':
                // Published -> Celebrate with Creator, SMM & Sales
                $recipients = [$post->assigned_to, $post->created_by, $clientSmmId];
                $icon = 'fa-globe';
                $bgColor = 'bg-teal-600';
                $actionHint = "Now live on {$post->platform_info['label']}!";
                break;
        }

        // Deduplicate & avoid notifying the actor who triggered the stage move
        $targetUserIds = array_values(array_unique(array_filter($recipients, fn($id) => !empty($id) && (int)$id !== (int)$actorId)));

        foreach ($targetUserIds as $userId) {
            Notification::create([
                'user_id'       => $userId,
                'icon'          => $icon,
                'bg_color'      => $bgColor,
                'message'       => "[6-Stage Workflow -> {$stageLabel}] {$formatLabel} '{$post->title}' ({$clientName}) moved by {$actorName}. {$actionHint}",
                'is_admin_only' => false,
            ]);
        }
    }

    public function destroy(ContentPost $post)
    {
        $post->delete();

        return redirect()->back()->with('success', 'Content item deleted successfully.');
    }
}
