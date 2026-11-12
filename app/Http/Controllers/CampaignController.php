<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{Campaign, Client, User, Task, Notification};
use Illuminate\Support\Facades\{Auth, DB};

class CampaignController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Campaign::with(['client', 'assignedTo', 'createdBy']);

        // Scoping for non-admins
        if ($user && !$user->isOwner() && !$user->isAdmin()) {
            if ($user->role === 'mediabuyer') {
                $query->where(function ($q) use ($user) {
                    $q->where('assigned_to', $user->id)
                      ->orWhere('created_by', $user->id);
                });
            } elseif ($user->role === 'smm') {
                $clientIds = Client::where('assigned_smm', $user->id)->pluck('id');
                $query->where(function ($q) use ($clientIds, $user) {
                    $q->whereIn('client_id', $clientIds)
                      ->orWhere('assigned_to', $user->id);
                });
            }
        }

        // Filtering
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('platform')) {
            $query->where('platform', $request->platform);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('objective')) {
            $query->where('objective', $request->objective);
        }

        // Summary metrics query (across filtered or total)
        $summaryQuery = clone $query;
        $totalBudget      = (float) $summaryQuery->sum('budget');
        $totalSpend       = (float) $summaryQuery->sum('spend');
        $totalRevenue     = (float) $summaryQuery->sum('revenue');
        $totalLeads       = (int)   $summaryQuery->sum('leads_count');
        $totalClicks      = (int)   $summaryQuery->sum('clicks');
        $totalImpressions = (int)   $summaryQuery->sum('impressions');

        $blendedCpl  = $totalLeads > 0 ? round($totalSpend / $totalLeads, 2) : 0.0;
        $blendedCtr  = $totalImpressions > 0 ? round(($totalClicks / $totalImpressions) * 100, 2) : 0.0;
        $blendedRoas = $totalSpend > 0 ? round($totalRevenue / $totalSpend, 2) : 0.0;
        $blendedCpc  = $totalClicks > 0 ? round($totalSpend / $totalClicks, 2) : 0.0;

        $campaigns = $query->orderBy('created_at', 'desc')->get();

        if ($request->wantsJson()) {
            return response()->json([
                'campaigns' => $campaigns,
                'metrics' => [
                    'budget'      => $totalBudget,
                    'spend'       => $totalSpend,
                    'revenue'     => $totalRevenue,
                    'leads'       => $totalLeads,
                    'cpl'         => $blendedCpl,
                    'ctr'         => $blendedCtr,
                    'roas'        => $blendedRoas,
                    'cpc'         => $blendedCpc,
                ]
            ]);
        }

        $clients = Client::orderBy('name')->get();
        $mediaBuyers = User::where('active', true)->orderBy('name')->get();

        return view('campaigns.index', compact(
            'campaigns',
            'clients',
            'mediaBuyers',
            'totalBudget',
            'totalSpend',
            'totalRevenue',
            'totalLeads',
            'totalClicks',
            'totalImpressions',
            'blendedCpl',
            'blendedCtr',
            'blendedRoas',
            'blendedCpc'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                    => 'required|string|max:255',
            'client_id'               => 'required|exists:clients,id',
            'platform'                => 'required|string|max:50',
            'objective'               => 'required|string|max:50',
            'status'                  => 'required|string|max:50',
            'budget'                  => 'nullable|numeric|min:0',
            'spend'                   => 'nullable|numeric|min:0',
            'revenue'                 => 'nullable|numeric|min:0',
            'impressions'             => 'nullable|integer|min:0',
            'clicks'                  => 'nullable|integer|min:0',
            'leads_count'             => 'nullable|integer|min:0',
            'start_date'              => 'nullable|date',
            'end_date'                => 'nullable|date|after_or_equal:start_date',
            'target_audience'         => 'nullable|string',
            'notes'                   => 'nullable|string',
            'ad_account_id'           => 'nullable|string|max:100',
            'assigned_to'             => 'nullable|exists:users,id',
            'tasks'                   => 'nullable|array',
            'tasks.*.title'           => 'required_with:tasks|string|max:255',
            'tasks.*.assigned_to'     => 'nullable|exists:users,id',
            'tasks.*.priority'        => 'nullable|in:low,medium,high',
            'tasks.*.deadline'        => 'nullable|date',
            'tasks.*.estimated_hours' => 'nullable|numeric|min:0.25|max:200',
        ]);

        $campaignData = collect($validated)->except(['tasks'])->all();
        $campaignData['created_by']  = Auth::id() ?? 1;
        $campaignData['budget']      = $campaignData['budget'] ?? 0;
        $campaignData['spend']       = $campaignData['spend'] ?? 0;
        $campaignData['revenue']     = $campaignData['revenue'] ?? 0;
        $campaignData['impressions'] = $campaignData['impressions'] ?? 0;
        $campaignData['clicks']      = $campaignData['clicks'] ?? 0;
        $campaignData['leads_count'] = $campaignData['leads_count'] ?? 0;

        $tasksCreatedCount = 0;

        DB::transaction(function () use ($campaignData, $request, &$tasksCreatedCount) {
            $campaign = Campaign::create($campaignData);
            $tasksInput = $request->input('tasks', []);
            if (!empty($tasksInput)) {
                $tasksCreatedCount = $this->createCampaignTasksBatch($campaign, $tasksInput);
            }
        });

        $msg = 'Ad campaign launched successfully!';
        if ($tasksCreatedCount > 0) {
            $msg = "Ad campaign launched with {$tasksCreatedCount} bulk-assigned task(s)!";
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Bulk assign 1 to 15+ team tasks to an existing campaign in one submission.
     */
    public function bulkAssignTasks(Request $request, Campaign $campaign)
    {
        $validated = $request->validate([
            'tasks'                   => 'required|array|min:1',
            'tasks.*.title'           => 'required|string|max:255',
            'tasks.*.assigned_to'     => 'nullable|exists:users,id',
            'tasks.*.priority'        => 'nullable|in:low,medium,high',
            'tasks.*.deadline'        => 'nullable|date',
            'tasks.*.estimated_hours' => 'nullable|numeric|min:0.25|max:200',
        ]);

        $count = 0;
        DB::transaction(function () use ($campaign, $validated, &$count) {
            $count = $this->createCampaignTasksBatch($campaign, $validated['tasks']);
        });

        return redirect()->back()->with('success', "Bulk assigned {$count} task(s) for campaign '{$campaign->name}'!");
    }

    private function createCampaignTasksBatch(Campaign $campaign, array $tasksInput): int
    {
        $actorId = Auth::id() ?? 1;
        $actorName = Auth::user()->name ?? 'Manager';
        $platformLabel = $campaign->platform_info['label'] ?? ucfirst($campaign->platform);

        $createdCount = 0;
        $assigneeCounts = [];

        foreach ($tasksInput as $row) {
            $title = trim((string)($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $assigneeId = !empty($row['assigned_to']) ? (int)$row['assigned_to'] : ($campaign->assigned_to ?: $actorId);
            $priority = in_array($row['priority'] ?? '', ['low', 'medium', 'high']) ? $row['priority'] : 'medium';

            Task::create([
                'title'           => $title,
                'client_id'       => $campaign->client_id,
                'assigned_to'     => $assigneeId,
                'assigned_by'     => $actorId,
                'status'          => 'pending',
                'priority'        => $priority,
                'approval_status' => 'pending',
                'deadline'        => !empty($row['deadline']) ? $row['deadline'] : ($campaign->start_date ? $campaign->start_date->format('Y-m-d') : now()->addDays(3)->format('Y-m-d')),
                'estimated_hours' => !empty($row['estimated_hours']) ? $row['estimated_hours'] : 2.0,
                'qty'             => 1,
                'notes'           => "Campaign Deliverable — {$campaign->name} ({$platformLabel})",
            ]);

            $createdCount++;
            if ($assigneeId && $assigneeId !== (int)$actorId) {
                $assigneeCounts[$assigneeId] = ($assigneeCounts[$assigneeId] ?? 0) + 1;
            }
        }

        // Send 1 clean summary notification per assignee instead of flooding
        foreach ($assigneeCounts as $userId => $num) {
            Notification::create([
                'user_id'       => $userId,
                'icon'          => 'fa-clipboard-check',
                'bg_color'      => 'bg-blue-600',
                'message'       => "Campaign Tasks Assigned: {$actorName} assigned {$num} new task(s) to you for '{$campaign->name}'.",
                'is_admin_only' => false,
            ]);
        }

        return $createdCount;
    }

    public function update(Request $request, Campaign $campaign)
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'client_id'       => 'required|exists:clients,id',
            'platform'        => 'required|string|max:50',
            'objective'       => 'required|string|max:50',
            'status'          => 'required|string|max:50',
            'budget'          => 'nullable|numeric|min:0',
            'spend'           => 'nullable|numeric|min:0',
            'revenue'         => 'nullable|numeric|min:0',
            'impressions'     => 'nullable|integer|min:0',
            'clicks'          => 'nullable|integer|min:0',
            'leads_count'     => 'nullable|integer|min:0',
            'start_date'      => 'nullable|date',
            'end_date'        => 'nullable|date|after_or_equal:start_date',
            'target_audience' => 'nullable|string',
            'notes'           => 'nullable|string',
            'ad_account_id'   => 'nullable|string|max:100',
            'assigned_to'     => 'nullable|exists:users,id',
        ]);

        $campaign->update($validated);

        return redirect()->back()->with('success', 'Campaign details updated successfully!');
    }

    public function updateMetrics(Request $request, Campaign $campaign)
    {
        $validated = $request->validate([
            'spend'       => 'required|numeric|min:0',
            'revenue'     => 'nullable|numeric|min:0',
            'impressions' => 'nullable|integer|min:0',
            'clicks'      => 'nullable|integer|min:0',
            'leads_count' => 'nullable|integer|min:0',
        ]);

        $campaign->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Campaign metrics updated',
                'cpl'     => $campaign->cpl,
                'ctr'     => $campaign->ctr,
                'roas'    => $campaign->roas,
            ]);
        }

        return redirect()->back()->with('success', 'Campaign live metrics updated!');
    }

    public function updateStatus(Request $request, Campaign $campaign)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:active,paused,completed,draft',
        ]);

        $campaign->update($validated);

        return redirect()->back()->with('success', 'Campaign status updated to ' . ucfirst($campaign->status));
    }

    public function destroy(Campaign $campaign)
    {
        $campaign->delete();

        return redirect()->back()->with('success', 'Campaign deleted successfully.');
    }
}
