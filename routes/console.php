<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('user:reset-password {username} {password}', function (string $username, string $password) {
    if (strlen($password) < 6) {
        $this->error('Password must be at least 6 characters.');

        return self::FAILURE;
    }

    $user = User::where('username', strtolower($username))->first();

    if (! $user) {
        $this->error("User [{$username}] was not found.");

        return self::FAILURE;
    }

    $user->forceFill([
        'password' => $password,
        'active' => true,
    ])->save();

    $this->info("Password reset for [{$user->username}].");

    return self::SUCCESS;
})->purpose('Reset a user password and reactivate the account');

Artisan::command('user:check-password {username} {password}', function (string $username, string $password) {
    $user = User::where('username', strtolower($username))->first();

    if (! $user || ! \Illuminate\Support\Facades\Hash::check($password, $user->password)) {
        $this->error('Password check failed.');

        return self::FAILURE;
    }

    $this->info('Password check passed.');

    return self::SUCCESS;
})->purpose('Check a user password against the stored hash');

// Automated CRM Notification & Alerts Dispatcher (REQ-NOT-01)
Artisan::command('crm:check-alerts', function () {
    $this->info('Scanning CRM for overdue invoices, tasks, pending SMM worklogs, and contract milestones...');
    $count = \App\Http\Controllers\NotificationController::generateAutomatedAlerts();
    $this->info("CRM Automated Alerts check complete! Generated {$count} new notification(s).");
    return self::SUCCESS;
})->purpose('Check and generate automated notifications for overdue invoices, tasks, and contract renewals')->dailyAt('09:00');

// Automated Monthly Retainer Recurring Billing Generator (REQ-BIL-01)
Artisan::command('invoices:generate-recurring {month?}', function (?string $month = null) {
    $targetMonth = $month ?: now()->format('Y-m');
    $this->info("Running automated recurring retainer billing for month: {$targetMonth}...");
    $result = \App\Http\Controllers\InvoiceController::generateRecurringInvoices($targetMonth);
    $this->info("Recurring billing complete! Generated {$result['generated_count']} invoice(s) totaling BDT " . number_format($result['total_amount'], 2) . ".");
    return self::SUCCESS;
})->purpose('Generate monthly retainer invoices for active clients')->monthlyOn(1, '01:00');

// Clean Database Reset (Preserves Owner Admin ID 1)
Artisan::command('db:reset-clean {--force : Bypass safety confirmation prompt} {--wipe-catalogs : Also truncate services and expense categories}', function () {
    $force = $this->option('force');
    $wipeCatalogs = (bool) $this->option('wipe-catalogs');

    if (! $force) {
        $confirmed = $this->confirm(
            '⚠️  WARNING: This will permanently DELETE all clients, leads, tasks, campaigns, invoices, expenses, and employee accounts, PRESERVING only the primary Owner Admin. Do you want to proceed?',
            false
        );

        if (! $confirmed) {
            $this->comment('Reset operation cancelled by user.');
            return self::SUCCESS;
        }
    }

    $this->warn('Beginning clean database reset...');
    $res = \App\Services\DatabaseResetService::resetExceptOwnerAdmin(! $wipeCatalogs);

    $this->info("✅ Successfully truncated " . count($res['truncated_tables']) . " operational data tables.");
    $this->info("✅ Removed {$res['deleted_users_count']} demo/staff user accounts.");
    if ($res['preserved_owner']) {
        $owner = $res['preserved_owner'];
        $this->info("👑 Preserved Owner Admin: [ID: {$owner['id']}] {$owner['username']} ({$owner['name']}) <{$owner['email']}> - Role: {$owner['role']}");
    }
    $this->info('Database reset complete!');

    return self::SUCCESS;
})->purpose('Reset all database records while preserving the Owner Admin account');

