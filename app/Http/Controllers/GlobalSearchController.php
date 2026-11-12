<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\{Client, Task, Lead, Campaign, Requisition, Invoice, Meeting};

class GlobalSearchController extends Controller
{
    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['results' => [], 'total' => 0]);
        }

        $user = Auth::user();
        $results = [];

        // 1. Clients
        if ($user->canAccess('clients')) {
            $clients = Client::where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('company', 'like', "%{$q}%")
                      ->orWhere('phone', 'like', "%{$q}%")
                      ->orWhere('email', 'like', "%{$q}%");
            })->limit(5)->get();

            foreach ($clients as $client) {
                $results[] = [
                    'category' => 'Clients',
                    'title'    => $client->name,
                    'subtitle' => ($client->company ?: 'Client Account') . ' • ' . ucfirst($client->status),
                    'url'      => route('clients.show', $client->id),
                    'icon'     => 'fa-building',
                    'badgeBg'  => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                    'iconBg'   => 'bg-emerald-100 text-emerald-700',
                ];
            }
        }

        // 2. Tasks & Deliverables
        if ($user->canAccess('tasks') || $user->canAccess('my_tasks')) {
            $tasksQuery = Task::with(['client', 'assignedTo'])
                ->where(function ($query) use ($q) {
                    $query->where('title', 'like', "%{$q}%")
                          ->orWhere('notes', 'like', "%{$q}%");
                });

            if (!$user->isAdmin()) {
                $tasksQuery->where(function ($query) use ($user) {
                    $query->where('assigned_to', $user->id)
                          ->orWhere('assigned_by', $user->id);
                });
            }

            foreach ($tasksQuery->limit(5)->get() as $task) {
                $results[] = [
                    'category' => 'Tasks',
                    'title'    => $task->title,
                    'subtitle' => ($task->client->name ?? 'Internal') . ' • ' . ucfirst(str_replace('_', ' ', $task->status)) . ($task->assignedTo ? ' (' . $task->assignedTo->name . ')' : ''),
                    'url'      => route('tasks.show', $task->id),
                    'icon'     => 'fa-list-check',
                    'badgeBg'  => 'bg-amber-50 text-amber-700 border-amber-200',
                    'iconBg'   => 'bg-amber-100 text-amber-700',
                ];
            }
        }

        // 3. Ad Campaigns / Projects
        if ($user->canAccess('campaigns')) {
            $campaignsQuery = Campaign::with('client')
                ->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                          ->orWhere('ad_account_id', 'like', "%{$q}%")
                          ->orWhere('target_audience', 'like', "%{$q}%");
                });

            if (!$user->isAdmin() && $user->role === 'mediabuyer') {
                $campaignsQuery->where(function ($query) use ($user) {
                    $query->where('assigned_to', $user->id)->orWhere('created_by', $user->id);
                });
            }

            foreach ($campaignsQuery->limit(5)->get() as $camp) {
                $results[] = [
                    'category' => 'Projects & Campaigns',
                    'title'    => $camp->name,
                    'subtitle' => ($camp->client->name ?? 'Client') . ' • ' . ($camp->platform_info['label'] ?? ucfirst($camp->platform)) . ' (' . ucfirst($camp->status) . ')',
                    'url'      => route('campaigns.index', ['client_id' => $camp->client_id]),
                    'icon'     => 'fa-bullseye',
                    'badgeBg'  => 'bg-blue-50 text-blue-700 border-blue-200',
                    'iconBg'   => 'bg-blue-100 text-blue-700',
                ];
            }
        }

        // 4. CRM Leads & Deals
        if ($user->canAccess('crm')) {
            $leadsQuery = Lead::with('stage')
                ->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                          ->orWhere('company', 'like', "%{$q}%")
                          ->orWhere('phone', 'like', "%{$q}%")
                          ->orWhere('email', 'like', "%{$q}%");
                });

            if (!$user->isAdmin()) {
                $leadsQuery->where('assigned_to', $user->id);
            }

            foreach ($leadsQuery->limit(5)->get() as $lead) {
                $results[] = [
                    'category' => 'CRM Leads',
                    'title'    => $lead->name,
                    'subtitle' => ($lead->company ?: 'Prospect') . ' • ' . ($lead->stage->name ?? 'Pipeline'),
                    'url'      => route('crm.show', $lead->id),
                    'icon'     => 'fa-funnel-dollar',
                    'badgeBg'  => 'bg-purple-50 text-purple-700 border-purple-200',
                    'iconBg'   => 'bg-purple-100 text-purple-700',
                ];
            }
        }

        // 5. Sales Requisitions (Projects Onboarding)
        if ($user->canAccess('requisitions')) {
            $reqs = Requisition::where(function ($query) use ($q) {
                $query->where('client_name', 'like', "%{$q}%")
                      ->orWhere('company', 'like', "%{$q}%");
            })->limit(4)->get();

            foreach ($reqs as $req) {
                $results[] = [
                    'category' => 'Requisitions',
                    'title'    => "Req #{$req->id} — {$req->client_name}",
                    'subtitle' => ($req->company ?: 'Onboarding Project') . ' • ' . ucfirst($req->status),
                    'url'      => route('requisitions.show', $req->id),
                    'icon'     => 'fa-clipboard-check',
                    'badgeBg'  => 'bg-teal-50 text-teal-700 border-teal-200',
                    'iconBg'   => 'bg-teal-100 text-teal-700',
                ];
            }
        }

        // 6. Invoices
        if ($user->canAccess('invoices')) {
            $invoices = Invoice::with('client')
                ->where(function ($query) use ($q) {
                    $query->where('invoice_number', 'like', "%{$q}%")
                          ->orWhereHas('client', fn($cq) => $cq->where('name', 'like', "%{$q}%"));
                })->limit(4)->get();

            foreach ($invoices as $inv) {
                $results[] = [
                    'category' => 'Invoices',
                    'title'    => $inv->invoice_number,
                    'subtitle' => ($inv->client->name ?? 'Client') . ' • ৳' . number_format($inv->total, 0) . ' (' . ucfirst($inv->status) . ')',
                    'url'      => route('invoices.show', $inv->id),
                    'icon'     => 'fa-file-invoice-dollar',
                    'badgeBg'  => 'bg-rose-50 text-rose-700 border-rose-200',
                    'iconBg'   => 'bg-rose-100 text-rose-700',
                ];
            }
        }

        // 7. Meetings
        if ($user->canAccess('meetings')) {
            $meetings = Meeting::where(function ($query) use ($q) {
                $query->where('agenda', 'like', "%{$q}%")
                      ->orWhere('client_name', 'like', "%{$q}%");
            })->limit(3)->get();

            foreach ($meetings as $mtg) {
                $results[] = [
                    'category' => 'Meetings',
                    'title'    => $mtg->agenda,
                    'subtitle' => ($mtg->client_name ?: 'Schedule') . ' • ' . ($mtg->date ? $mtg->date->format('d M') : ''),
                    'url'      => route('meetings.index', ['search' => $mtg->agenda]),
                    'icon'     => 'fa-calendar-check',
                    'badgeBg'  => 'bg-sky-50 text-sky-700 border-sky-200',
                    'iconBg'   => 'bg-sky-100 text-sky-700',
                ];
            }
        }

        return response()->json([
            'results' => $results,
            'total'   => count($results),
        ]);
    }
}
