<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\User;

class DatabaseResetService
{
    /**
     * Resets all business records, demo accounts, and operational data
     * while safely preserving the primary Owner Admin (ID 1 / role 'owner').
     *
     * @param bool $keepCatalogs Whether to keep base lookup catalogs (lead_stages, custom_statuses, reminder_templates, services, expense_categories)
     * @return array
     */
    public static function resetExceptOwnerAdmin(bool $keepCatalogs = true): array
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

        $tablesToTruncate = [
            'attendances',
            'campaigns',
            'client_services',
            'clients',
            'content_posts',
            'employee_salaries',
            'expenses',
            'followups',
            'invoice_items',
            'invoices',
            'lead_requirements',
            'lead_timelines',
            'leads',
            'meeting_attendees',
            'meetings',
            'my_work_items',
            'notifications',
            'reminders',
            'requisition_items',
            'requisition_team',
            'requisitions',
            'sessions',
            'target_deals',
            'target_items',
            'targets',
            'task_approval_comments',
            'task_progresses',
            'tasks',
            'worklogs',
        ];

        $truncatedTables = [];
        foreach ($tablesToTruncate as $table) {
            DB::table($table)->truncate();
            $truncatedTables[] = $table;
        }

        // Identify the primary owner admin to preserve
        $owner = User::where('role', 'owner')->orderBy('id', 'asc')->first()
            ?? User::where('username', 'admin')->orderBy('id', 'asc')->first()
            ?? User::where('id', 1)->first();

        $deletedUsersCount = 0;
        if ($owner) {
            // Delete all other users
            $deletedUsersCount = User::where('id', '!=', $owner->id)->delete();

            // Ensure owner role & active status
            $owner->update([
                'role' => 'owner',
                'active' => true,
            ]);
        }

        if (!$keepCatalogs) {
            DB::table('services')->truncate();
            DB::table('expense_categories')->truncate();
            DB::table('reminder_templates')->truncate();
        }

        // Always ensure essential CRM pipeline stages exist
        if (DB::table('lead_stages')->count() === 0) {
            DB::table('lead_stages')->insert([
                ['id' => 1, 'name' => 'New Lead',      'color' => '#94a3b8', 'order' => 1, 'is_won' => 0, 'is_lost' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['id' => 2, 'name' => 'Contacted',     'color' => '#3b82f6', 'order' => 2, 'is_won' => 0, 'is_lost' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['id' => 3, 'name' => 'Meeting Done',  'color' => '#8b5cf6', 'order' => 3, 'is_won' => 0, 'is_lost' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['id' => 4, 'name' => 'Proposal Sent', 'color' => '#f59e0b', 'order' => 4, 'is_won' => 0, 'is_lost' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['id' => 5, 'name' => 'Negotiation',   'color' => '#f97316', 'order' => 5, 'is_won' => 0, 'is_lost' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['id' => 6, 'name' => 'Won',           'color' => '#10b981', 'order' => 6, 'is_won' => 1, 'is_lost' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['id' => 7, 'name' => 'Lost',          'color' => '#ef4444', 'order' => 7, 'is_won' => 0, 'is_lost' => 1, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        // Always ensure essential task statuses exist
        if (DB::table('custom_statuses')->count() === 0) {
            DB::table('custom_statuses')->insert([
                ['id' => 1, 'name' => 'Backlog',          'color' => '#94a3b8', 'order' => 1, 'core_status' => 'pending',             'is_default' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['id' => 2, 'name' => 'In Progress',      'color' => '#3b82f6', 'order' => 2, 'core_status' => 'in_progress',         'is_default' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['id' => 3, 'name' => 'Quality Review',   'color' => '#8b5cf6', 'order' => 3, 'core_status' => 'done_pending_review', 'is_default' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['id' => 4, 'name' => 'Approved & Done',  'color' => '#10b981', 'order' => 4, 'core_status' => 'done',                'is_default' => 1, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');

        return [
            'success' => true,
            'truncated_tables' => $truncatedTables,
            'deleted_users_count' => $deletedUsersCount,
            'preserved_owner' => $owner ? [
                'id' => $owner->id,
                'name' => $owner->name,
                'username' => $owner->username,
                'role' => $owner->role,
                'email' => $owner->email,
            ] : null,
        ];
    }
}
