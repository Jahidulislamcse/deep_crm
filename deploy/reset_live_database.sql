-- ====================================================================
-- DMS CRM — LIVE DATABASE RESET SCRIPT (CLEAN SLATE)
-- ====================================================================
-- Description:
--   Permanently resets all demo & operational data (clients, leads,
--   tasks, campaigns, invoices, expenses, worklogs, attendance, etc.)
--   and removes all demo staff users while PRESERVING the primary
--   Owner Admin account (User ID: 1 / role: 'owner' / username: 'admin').
--
-- How to run in cPanel phpMyAdmin:
--   1. Log into your cPanel and open phpMyAdmin.
--   2. Click on your CRM database (e.g. `deepmark_main`).
--   3. Click on the "SQL" tab at the top.
--   4. Copy and paste all queries below, then click "Go".
-- ====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------------------
-- 1. TRUNCATE ALL OPERATIONAL & TRANSACTIONAL TABLES
-- --------------------------------------------------------------------
TRUNCATE TABLE `attendances`;
TRUNCATE TABLE `campaigns`;
TRUNCATE TABLE `client_services`;
TRUNCATE TABLE `clients`;
TRUNCATE TABLE `content_posts`;
TRUNCATE TABLE `employee_salaries`;
TRUNCATE TABLE `expenses`;
TRUNCATE TABLE `followups`;
TRUNCATE TABLE `invoice_items`;
TRUNCATE TABLE `invoices`;
TRUNCATE TABLE `lead_requirements`;
TRUNCATE TABLE `lead_timelines`;
TRUNCATE TABLE `leads`;
TRUNCATE TABLE `meeting_attendees`;
TRUNCATE TABLE `meetings`;
TRUNCATE TABLE `my_work_items`;
TRUNCATE TABLE `notifications`;
TRUNCATE TABLE `reminders`;
TRUNCATE TABLE `requisition_items`;
TRUNCATE TABLE `requisition_team`;
TRUNCATE TABLE `requisitions`;
TRUNCATE TABLE `sessions`;
TRUNCATE TABLE `target_deals`;
TRUNCATE TABLE `target_items`;
TRUNCATE TABLE `targets`;
TRUNCATE TABLE `task_approval_comments`;
TRUNCATE TABLE `task_progresses`;
TRUNCATE TABLE `tasks`;
TRUNCATE TABLE `worklogs`;

-- --------------------------------------------------------------------
-- 2. CLEAN USERS: REMOVE ALL DEMO/STAFF ACCOUNTS, KEEP OWNER ADMIN
-- --------------------------------------------------------------------
-- Delete all demo/staff users whose ID is NOT 1 and role is NOT 'owner'
DELETE FROM `users` WHERE `id` != 1 AND `role` != 'owner';

-- Ensure the remaining primary account has the 'owner' role and is active
UPDATE `users` SET `role` = 'owner', `active` = 1 WHERE `id` = 1 OR `role` = 'owner' LIMIT 1;

-- --------------------------------------------------------------------
-- 3. ENSURE ESSENTIAL CRM PIPELINE STAGES EXIST
-- --------------------------------------------------------------------
INSERT IGNORE INTO `lead_stages` (`id`, `name`, `color`, `order`, `is_won`, `is_lost`, `created_at`, `updated_at`) VALUES
(1, 'New Lead',      '#94a3b8', 1, 0, 0, NOW(), NOW()),
(2, 'Contacted',     '#3b82f6', 2, 0, 0, NOW(), NOW()),
(3, 'Meeting Done',  '#8b5cf6', 3, 0, 0, NOW(), NOW()),
(4, 'Proposal Sent', '#f59e0b', 4, 0, 0, NOW(), NOW()),
(5, 'Negotiation',   '#f97316', 5, 0, 0, NOW(), NOW()),
(6, 'Won',           '#10b981', 6, 1, 0, NOW(), NOW()),
(7, 'Lost',          '#ef4444', 7, 0, 1, NOW(), NOW());

-- --------------------------------------------------------------------
-- 4. ENSURE ESSENTIAL TASK CUSTOM STATUSES EXIST
-- --------------------------------------------------------------------
INSERT IGNORE INTO `custom_statuses` (`id`, `name`, `color`, `order`, `core_status`, `is_default`, `created_at`, `updated_at`) VALUES
(1, 'Backlog',          '#94a3b8', 1, 'pending',             1, NOW(), NOW()),
(2, 'In Progress',      '#3b82f6', 2, 'in_progress',         1, NOW(), NOW()),
(3, 'Quality Review',   '#8b5cf6', 3, 'done_pending_review', 1, NOW(), NOW()),
(4, 'Approved & Done',  '#10b981', 4, 'done',                1, NOW(), NOW());

SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------------------
-- VERIFICATION QUERY
-- --------------------------------------------------------------------
SELECT 'RESET COMPLETE' AS `status`, COUNT(*) AS `remaining_users` FROM `users`;
SELECT `id`, `name`, `username`, `email`, `role`, `active` FROM `users`;
