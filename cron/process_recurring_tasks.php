<?php
/**
 * Recurring Tasks Cron Job
 * ========================
 * Run this script daily via cron:
 * 0 8 * * * php /path/to/phidtech.com/cron/process_recurring_tasks.php
 * 
 * This script:
 * 1. Sends reminders for upcoming task due dates
 * 2. Notifies assigned staff and optionally customers
 * 3. Regenerates recurring tasks after completion
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/notifications.php';

// Log file
$logFile = __DIR__ . '/../logs/recurring_tasks.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

logMessage("=== Starting Recurring Tasks Processing ===");

try {
    $db = new Database();
    $conn = $db->getConnection();
    $notifier = new NotificationService($conn);
    
    $today = date('Y-m-d');
    
    // 1. Send reminders for upcoming tasks
    logMessage("Checking for task reminders...");
    
    $stmt = $conn->query("
        SELECT t.*, p.title as project_title, p.reference as project_ref, p.customer_id,
               u.name as assigned_name, u.id as assigned_user_id,
               c.name as customer_name, c.id as customer_user_id
        FROM tasks t
        JOIN projects p ON t.project_id = p.id
        LEFT JOIN users u ON t.assigned_to = u.id
        LEFT JOIN users c ON p.customer_id = c.id
        WHERE t.status NOT IN ('approved', 'rejected')
        AND t.due_date IS NOT NULL
        AND DATEDIFF(t.due_date, CURDATE()) = COALESCE(t.notify_days_before, 1)
    ");
    $upcomingTasks = $stmt->fetchAll();
    
    foreach ($upcomingTasks as $task) {
        $daysUntilDue = $task['notify_days_before'] ?? 1;
        
        // Notify assigned staff
        if ($task['assigned_user_id']) {
            $subject = "Task Reminder: {$task['title']}";
            $message = "Reminder: Your task \"{$task['title']}\" for project \"{$task['project_title']}\" is due in {$daysUntilDue} day(s) on {$task['due_date']}. Priority: " . ucfirst($task['priority']);
            
            if ($task['is_recurring']) {
                $message .= " (Recurring: " . ucfirst($task['recurrence_type']) . ")";
            }
            
            $notifier->send($task['assigned_user_id'], $subject, $message, 'task', 'task', $task['id']);
            logMessage("Sent reminder to staff ID {$task['assigned_user_id']} for task ID {$task['id']}");
        }
        
        // Notify customer if enabled
        if ($task['notify_customer'] && $task['customer_user_id']) {
            $subject = "Project Update: Task Due Soon - {$task['project_ref']}";
            $message = "A task \"{$task['title']}\" for your project \"{$task['project_title']}\" is scheduled for {$task['due_date']}. Our team is working on it.";
            
            $notifier->send($task['customer_user_id'], $subject, $message, 'task', 'task', $task['id']);
            logMessage("Sent reminder to customer ID {$task['customer_user_id']} for task ID {$task['id']}");
        }
    }
    
    logMessage("Processed " . count($upcomingTasks) . " task reminders");
    
    // 2. Process recurring tasks that are due today and need regeneration
    logMessage("Checking for recurring tasks to regenerate...");
    
    $stmt = $conn->query("
        SELECT t.*, p.title as project_title, p.customer_id
        FROM tasks t
        JOIN projects p ON t.project_id = p.id
        WHERE t.is_recurring = 1
        AND t.recurrence_type != 'none'
        AND t.status = 'approved'
        AND t.next_recurrence_date IS NOT NULL
        AND t.next_recurrence_date <= CURDATE()
        AND (t.recurrence_end_date IS NULL OR t.recurrence_end_date >= CURDATE())
    ");
    $recurringTasks = $stmt->fetchAll();
    
    foreach ($recurringTasks as $task) {
        // Calculate next due date based on recurrence type
        $currentDue = $task['next_recurrence_date'];
        $nextDue = null;
        
        switch ($task['recurrence_type']) {
            case 'daily':
                $nextDue = date('Y-m-d', strtotime($currentDue . ' +1 day'));
                break;
            case 'weekly':
                $nextDue = date('Y-m-d', strtotime($currentDue . ' +1 week'));
                break;
            case 'monthly':
                if ($task['recurrence_day']) {
                    $nextMonth = date('Y-m', strtotime($currentDue . ' +1 month'));
                    $nextDue = $nextMonth . '-' . str_pad($task['recurrence_day'], 2, '0', STR_PAD_LEFT);
                    // Handle months with fewer days
                    if (!checkdate(intval(date('m', strtotime($nextDue))), $task['recurrence_day'], intval(date('Y', strtotime($nextDue))))) {
                        $nextDue = date('Y-m-t', strtotime($nextMonth . '-01'));
                    }
                } else {
                    $nextDue = date('Y-m-d', strtotime($currentDue . ' +1 month'));
                }
                break;
            case 'yearly':
                if ($task['recurrence_month'] && $task['recurrence_day']) {
                    $nextYear = intval(date('Y', strtotime($currentDue))) + 1;
                    $nextDue = $nextYear . '-' . str_pad($task['recurrence_month'], 2, '0', STR_PAD_LEFT) . '-' . str_pad($task['recurrence_day'], 2, '0', STR_PAD_LEFT);
                } else {
                    $nextDue = date('Y-m-d', strtotime($currentDue . ' +1 year'));
                }
                break;
        }
        
        // Check if next due date is within the recurrence end date
        if ($task['recurrence_end_date'] && $nextDue > $task['recurrence_end_date']) {
            logMessage("Task ID {$task['id']} recurrence ended (end date: {$task['recurrence_end_date']})");
            continue;
        }
        
        // Create new task instance
        $stmt = $conn->prepare("
            INSERT INTO tasks (project_id, title, description, assigned_to, assigned_by, priority, due_date, status, 
                              is_recurring, recurrence_type, recurrence_day, recurrence_month, 
                              recurrence_start_date, recurrence_end_date, next_recurrence_date, 
                              notify_customer, notify_days_before, attachment)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $task['project_id'],
            $task['title'],
            $task['description'],
            $task['assigned_to'],
            $task['assigned_by'],
            $task['priority'],
            $nextDue,
            $task['is_recurring'],
            $task['recurrence_type'],
            $task['recurrence_day'],
            $task['recurrence_month'],
            $task['recurrence_start_date'],
            $task['recurrence_end_date'],
            $nextDue,
            $task['notify_customer'],
            $task['notify_days_before'],
            $task['attachment']
        ]);
        
        $newTaskId = $conn->lastInsertId();
        
        // Update old task to mark it as processed
        $stmt = $conn->prepare("UPDATE tasks SET next_recurrence_date = NULL, last_recurrence_date = ? WHERE id = ?");
        $stmt->execute([$today, $task['id']]);
        
        // Notify assigned staff about new recurring task
        if ($task['assigned_to']) {
            $subject = "Recurring Task: {$task['title']}";
            $message = "A new instance of recurring task \"{$task['title']}\" has been created for project \"{$task['project_title']}\". Due date: {$nextDue}. Priority: " . ucfirst($task['priority']);
            $notifier->send($task['assigned_to'], $subject, $message, 'task', 'task', $newTaskId);
        }
        
        // Notify customer if enabled
        if ($task['notify_customer'] && $task['customer_id']) {
            $subject = "Scheduled Task: {$task['title']}";
            $message = "A scheduled task \"{$task['title']}\" has been created for your project. Our team will work on it by {$nextDue}.";
            $notifier->send($task['customer_id'], $subject, $message, 'task', 'task', $newTaskId);
        }
        
        logMessage("Created new recurring task ID {$newTaskId} from task ID {$task['id']}, next due: {$nextDue}");
    }
    
    logMessage("Processed " . count($recurringTasks) . " recurring tasks");
    
    // 3. Send overdue task notifications
    logMessage("Checking for overdue tasks...");
    
    $stmt = $conn->query("
        SELECT t.*, p.title as project_title, u.id as assigned_user_id
        FROM tasks t
        JOIN projects p ON t.project_id = p.id
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE t.status NOT IN ('approved', 'rejected')
        AND t.due_date < CURDATE()
        AND DATEDIFF(CURDATE(), t.due_date) = 1
    ");
    $overdueTasks = $stmt->fetchAll();
    
    foreach ($overdueTasks as $task) {
        if ($task['assigned_user_id']) {
            $subject = "⚠️ Overdue Task: {$task['title']}";
            $message = "Your task \"{$task['title']}\" for project \"{$task['project_title']}\" is now OVERDUE. It was due on {$task['due_date']}. Please complete it as soon as possible.";
            $notifier->send($task['assigned_user_id'], $subject, $message, 'task', 'task', $task['id']);
            logMessage("Sent overdue notification to staff ID {$task['assigned_user_id']} for task ID {$task['id']}");
        }
        
        // Also notify managers about overdue tasks
        $notifier->sendToRoles([ROLE_MANAGER, ROLE_CEO], "Overdue Task Alert: {$task['title']}", 
            "Task \"{$task['title']}\" assigned to " . ($task['assigned_name'] ?? 'Unknown') . " is overdue. Due date was: {$task['due_date']}",
            'task', 'task', $task['id']);
    }
    
    logMessage("Processed " . count($overdueTasks) . " overdue task notifications");
    logMessage("=== Recurring Tasks Processing Complete ===\n");
    
} catch (Exception $e) {
    logMessage("ERROR: " . $e->getMessage());
}
