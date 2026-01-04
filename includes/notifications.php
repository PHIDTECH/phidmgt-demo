<?php
require_once __DIR__ . '/../config/database.php';

class NotificationService {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function send($recipientId, $subject, $message, $type = 'general', $refType = null, $refId = null, $senderId = null) {
        $stmt = $this->conn->prepare("
            INSERT INTO messages (sender_id, recipient_id, subject, message, type, reference_type, reference_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$senderId, $recipientId, $subject, $message, $type, $refType, $refId]);
    }
    
    public function sendToRole($role, $subject, $message, $type = 'general', $refType = null, $refId = null, $senderId = null) {
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE role = ? AND status = 'active'");
        $stmt->execute([$role]);
        $users = $stmt->fetchAll();
        
        foreach ($users as $user) {
            $this->send($user['id'], $subject, $message, $type, $refType, $refId, $senderId);
        }
    }
    
    public function sendToRoles($roles, $subject, $message, $type = 'general', $refType = null, $refId = null, $senderId = null) {
        $placeholders = str_repeat('?,', count($roles) - 1) . '?';
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE role IN ($placeholders) AND status = 'active'");
        $stmt->execute($roles);
        $users = $stmt->fetchAll();
        
        foreach ($users as $user) {
            $this->send($user['id'], $subject, $message, $type, $refType, $refId, $senderId);
        }
    }
    
    // Payment received notification
    public function notifyPaymentReceived($project, $payment, $customer) {
        $subject = "Payment Received - {$project['reference']}";
        $message = "Payment of " . formatCurrency($payment['amount']) . " has been received for project '{$project['title']}' from {$customer['name']}.";
        
        // Notify CEO and Accountant
        $this->sendToRoles([ROLE_CEO, ROLE_ACCOUNTANT], $subject, $message, 'payment', 'payment', $payment['id']);
        
        // Notify Customer
        $customerMsg = "Your payment of " . formatCurrency($payment['amount']) . " for '{$project['title']}' has been received. Thank you!";
        $this->send($customer['id'], "Payment Confirmed - {$project['reference']}", $customerMsg, 'payment', 'payment', $payment['id']);
    }
    
    // Task assigned notification
    public function notifyTaskAssigned($task, $project, $assignedBy) {
        $subject = "New Task Assigned - {$task['title']}";
        $message = "You have been assigned a new task '{$task['title']}' for project '{$project['title']}' by {$assignedBy['name']}. Due: " . formatDate($task['due_date']);
        
        $this->send($task['assigned_to'], $subject, $message, 'task', 'task', $task['id'], $assignedBy['id']);
    }
    
    // Task completed notification
    public function notifyTaskCompleted($task, $project, $completedBy) {
        $subject = "Task Completed - {$task['title']}";
        $message = "{$completedBy['name']} has completed task '{$task['title']}' for project '{$project['title']}'. Please review.";
        
        // Notify Manager and CEO
        $this->sendToRoles([ROLE_MANAGER, ROLE_CEO], $subject, $message, 'task', 'task', $task['id'], $completedBy['id']);
        
        // If assigned by someone else, notify them too
        if ($task['assigned_by'] && $task['assigned_by'] != $completedBy['id']) {
            $this->send($task['assigned_by'], $subject, $message, 'task', 'task', $task['id'], $completedBy['id']);
        }
    }
    
    // Project completed notification
    public function notifyProjectCompleted($project, $customer) {
        $subject = "Project Completed - {$project['reference']}";
        $message = "Project '{$project['title']}' has been completed successfully.";
        
        // Notify Customer
        $customerMsg = "Great news! Your project '{$project['title']}' has been completed. Please login to review.";
        $this->send($customer['id'], $subject, $customerMsg, 'project', 'project', $project['id']);
        
        // Notify Manager and CEO
        $this->sendToRoles([ROLE_MANAGER, ROLE_CEO], $subject, $message, 'project', 'project', $project['id']);
    }
    
    // Leave application notification
    public function notifyLeaveApplied($leave, $applicant) {
        $subject = "Leave Application - {$applicant['name']}";
        $days = $leave['days'];
        $message = "{$applicant['name']} has applied for {$leave['leave_type']} leave for {$days} day(s) from " . formatDate($leave['start_date']) . " to " . formatDate($leave['end_date']) . ".";
        
        // Notify HR and Manager
        $this->sendToRoles([ROLE_HR, ROLE_MANAGER], $subject, $message, 'leave', 'leave', $leave['id'], $applicant['id']);
    }
    
    // Leave approved/rejected notification
    public function notifyLeaveDecision($leave, $applicant, $decision, $approver) {
        $status = $decision === 'approved' ? 'Approved' : 'Rejected';
        $subject = "Leave {$status}";
        $message = "Your {$leave['leave_type']} leave request from " . formatDate($leave['start_date']) . " to " . formatDate($leave['end_date']) . " has been {$status} by {$approver['name']}.";
        
        $this->send($applicant['id'], $subject, $message, 'leave', 'leave', $leave['id'], $approver['id']);
    }
    
    // New order notification
    public function notifyNewOrder($project, $customer) {
        $subject = "New Order - {$project['reference']}";
        $message = "New order '{$project['title']}' placed by {$customer['name']}. Amount: " . formatCurrency($project['total_amount']);
        
        // Notify CEO and Manager
        $this->sendToRoles([ROLE_CEO, ROLE_MANAGER], $subject, $message, 'project', 'project', $project['id']);
    }
    
    // Get unread count for user
    public function getUnreadCount($userId) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM messages WHERE recipient_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }
    
    // Get messages for user
    public function getMessages($userId, $limit = 50, $unreadOnly = false) {
        $sql = "SELECT m.*, u.name as sender_name 
                FROM messages m 
                LEFT JOIN users u ON m.sender_id = u.id 
                WHERE m.recipient_id = ?";
        if ($unreadOnly) {
            $sql .= " AND m.is_read = 0";
        }
        $sql .= " ORDER BY m.created_at DESC LIMIT ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }
    
    // Mark message as read
    public function markAsRead($messageId, $userId) {
        $stmt = $this->conn->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND recipient_id = ?");
        return $stmt->execute([$messageId, $userId]);
    }
    
    // Mark all as read
    public function markAllAsRead($userId) {
        $stmt = $this->conn->prepare("UPDATE messages SET is_read = 1 WHERE recipient_id = ? AND is_read = 0");
        return $stmt->execute([$userId]);
    }
}
