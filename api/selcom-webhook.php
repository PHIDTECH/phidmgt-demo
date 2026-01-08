<?php
/**
 * Selcom Payment Webhook Handler
 * ===============================
 * This endpoint receives payment notifications from Selcom
 * and automatically:
 * 1. Creates customer in customers list (only on successful payment)
 * 2. Records revenue
 * 3. Updates project payment status
 * 
 * Configure this URL in Selcom Dashboard: https://phidtech.com/api/selcom-webhook.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/notifications.php';

// Set JSON response header
header('Content-Type: application/json');

// Log incoming requests for debugging
$logFile = __DIR__ . '/../logs/selcom_webhook.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Get raw POST data
$rawInput = file_get_contents('php://input');
$timestamp = date('Y-m-d H:i:s');

// Log the request
file_put_contents($logFile, "[$timestamp] ========== NEW WEBHOOK ==========\n", FILE_APPEND);
file_put_contents($logFile, "[$timestamp] Received: $rawInput\n", FILE_APPEND);

// Parse JSON input
$data = json_decode($rawInput, true);

if (!$data) {
    file_put_contents($logFile, "[$timestamp] ERROR: Invalid JSON payload\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Extract Selcom payment data (field names based on Selcom API documentation)
    $transactionId = $data['transid'] ?? $data['transaction_id'] ?? $data['reference'] ?? null;
    $orderId = $data['order_id'] ?? $data['reference'] ?? '';
    $amount = floatval($data['amount'] ?? 0);
    $status = $data['result'] ?? $data['payment_status'] ?? $data['status'] ?? 'PENDING';
    $customerPhone = $data['msisdn'] ?? $data['buyer_phone'] ?? $data['phone'] ?? '';
    $customerEmail = $data['buyer_email'] ?? $data['email'] ?? '';
    $customerName = $data['buyer_name'] ?? $data['name'] ?? '';
    $paymentDate = $data['payment_date'] ?? date('Y-m-d');
    
    // Determine if payment was successful
    $isSuccess = in_array(strtoupper($status), ['SUCCESS', 'COMPLETED', 'SUCCESSFUL', 'PAID']);
    $paymentStatus = $isSuccess ? 'completed' : 'failed';
    
    // =====================================================
    // CHECK IF THIS IS A DIGITAL PRODUCT ORDER (DIG prefix)
    // =====================================================
    if (strpos($orderId, 'DIG') === 0) {
        file_put_contents($logFile, "[$timestamp] DIGITAL ORDER DETECTED: $orderId\n", FILE_APPEND);
        
        // Find digital order
        $stmt = $conn->prepare("SELECT do.*, dp.name as product_name, dp.file_path FROM digital_orders do JOIN digital_products dp ON do.product_id = dp.id WHERE do.order_reference = ?");
        $stmt->execute([$orderId]);
        $digitalOrder = $stmt->fetch();
        
        if ($digitalOrder) {
            if ($isSuccess) {
                // Update digital order to completed
                $stmt = $conn->prepare("UPDATE digital_orders SET payment_status = 'completed', transaction_id = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$transactionId, $digitalOrder['id']]);
                
                // Create customer account if new
                $customerId = $digitalOrder['customer_id'];
                if (!$customerId && $digitalOrder['customer_email']) {
                    // Check if customer exists
                    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt->execute([$digitalOrder['customer_email']]);
                    $existingUser = $stmt->fetch();
                    
                    if ($existingUser) {
                        $customerId = $existingUser['id'];
                    } else {
                        // Create new customer
                        $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role, status, created_at) VALUES (?, ?, ?, ?, 'customer', 'active', NOW())");
                        $stmt->execute([
                            $digitalOrder['customer_name'],
                            $digitalOrder['customer_email'],
                            $digitalOrder['customer_phone'],
                            $digitalOrder['password_hash'] ?: password_hash(uniqid(), PASSWORD_DEFAULT)
                        ]);
                        $customerId = $conn->lastInsertId();
                    }
                    
                    // Update order with customer ID
                    $stmt = $conn->prepare("UPDATE digital_orders SET customer_id = ? WHERE id = ?");
                    $stmt->execute([$customerId, $digitalOrder['id']]);
                }
                
                // Record revenue
                $stmt = $conn->prepare("INSERT INTO revenue (customer_id, description, amount, category, payment_method, transaction_reference, payment_status, date) VALUES (?, ?, ?, 'Digital Product', 'Selcom', ?, 'completed', ?)");
                $stmt->execute([$customerId, 'Digital Product: ' . $digitalOrder['product_name'], $digitalOrder['amount'], $transactionId, $paymentDate]);
                
                // Send download email
                $downloadUrl = SITE_URL . '/download.php?token=' . $digitalOrder['download_token'];
                $successUrl = SITE_URL . '/download-success.php?order=' . $orderId;
                
                // Try to send email notification
                $to = $digitalOrder['customer_email'];
                $subject = "Your Download is Ready - " . $digitalOrder['product_name'];
                $message = "Hello " . $digitalOrder['customer_name'] . ",\n\n";
                $message .= "Thank you for your purchase! Your payment has been confirmed.\n\n";
                $message .= "Product: " . $digitalOrder['product_name'] . "\n";
                $message .= "Order Reference: " . $orderId . "\n";
                $message .= "Amount Paid: TZS " . number_format($digitalOrder['amount']) . "\n\n";
                $message .= "Download your file here:\n" . $downloadUrl . "\n\n";
                $message .= "Note: This link expires in 7 days and allows up to 5 downloads.\n\n";
                $message .= "Thank you for choosing PhidTech!\n";
                $headers = "From: PhidTech <noreply@phidtech.com>";
                
                @mail($to, $subject, $message, $headers);
                
                file_put_contents($logFile, "[$timestamp] DIGITAL SUCCESS: Order $orderId completed. Download: $downloadUrl\n", FILE_APPEND);
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Digital product payment processed',
                    'order_id' => $orderId,
                    'download_url' => $downloadUrl
                ]);
                exit;
            } else {
                // Payment failed
                $stmt = $conn->prepare("UPDATE digital_orders SET payment_status = 'failed', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$digitalOrder['id']]);
                
                file_put_contents($logFile, "[$timestamp] DIGITAL FAILED: Order $orderId payment failed\n", FILE_APPEND);
                
                echo json_encode(['status' => 'failed', 'message' => 'Digital product payment failed']);
                exit;
            }
        }
    }
    // =====================================================
    // END DIGITAL PRODUCT HANDLING
    // =====================================================
    
    file_put_contents($logFile, "[$timestamp] Parsed: OrderID=$orderId, TransID=$transactionId, Amount=$amount, Status=$status, isSuccess=" . ($isSuccess ? 'YES' : 'NO') . "\n", FILE_APPEND);
    file_put_contents($logFile, "[$timestamp] Customer: Name=$customerName, Email=$customerEmail, Phone=$customerPhone\n", FILE_APPEND);
    
    // Check if this transaction was already processed
    $stmt = $conn->prepare("SELECT id FROM revenue WHERE transaction_reference = ?");
    $stmt->execute([$transactionId]);
    if ($stmt->fetch()) {
        file_put_contents($logFile, "[$timestamp] SKIP: Duplicate transaction $transactionId\n", FILE_APPEND);
        echo json_encode(['status' => 'ok', 'message' => 'Transaction already processed']);
        exit;
    }
    
    // Look up pending payment by order_id
    $pendingPayment = null;
    $projectId = null;
    $customerPasswordHash = null;
    
    if ($orderId) {
        $stmt = $conn->prepare("SELECT * FROM pending_payments WHERE selcom_order_id = ? OR selcom_transid = ?");
        $stmt->execute([$orderId, $transactionId]);
        $pendingPayment = $stmt->fetch();
        
        if ($pendingPayment) {
            $projectId = $pendingPayment['project_id'];
            // Use stored customer info if not provided by Selcom
            $customerName = $customerName ?: $pendingPayment['customer_name'];
            $customerEmail = $customerEmail ?: $pendingPayment['customer_email'];
            $customerPhone = $customerPhone ?: $pendingPayment['customer_phone'];
            $customerPasswordHash = $pendingPayment['customer_password'] ?? null;
            $amount = $amount ?: $pendingPayment['amount'];
            
            file_put_contents($logFile, "[$timestamp] Found pending payment: ProjectID=$projectId, Email=$customerEmail\n", FILE_APPEND);
        }
    }
    
    // Get project info if we have project_id
    $project = null;
    if ($projectId) {
        $stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$projectId]);
        $project = $stmt->fetch();
    }
    
    $description = $project ? ('Payment for ' . $project['title'] . ' (' . $project['reference'] . ')') : ('Selcom Payment - ' . $orderId);
    
    // Try to find existing customer by email or phone
    $customerId = null;
    
    if ($customerEmail) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND role = 'customer'");
        $stmt->execute([$customerEmail]);
        $customer = $stmt->fetch();
        if ($customer) {
            $customerId = $customer['id'];
            file_put_contents($logFile, "[$timestamp] Found existing customer by email: ID=$customerId\n", FILE_APPEND);
        }
    }
    
    if (!$customerId && $customerPhone) {
        $cleanPhone = preg_replace('/[^0-9]/', '', $customerPhone);
        if (strlen($cleanPhone) >= 9) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', '') LIKE ? AND role = 'customer'");
            $stmt->execute(['%' . substr($cleanPhone, -9)]);
            $customer = $stmt->fetch();
            if ($customer) {
                $customerId = $customer['id'];
                file_put_contents($logFile, "[$timestamp] Found existing customer by phone: ID=$customerId\n", FILE_APPEND);
            }
        }
    }
    
    // ONLY create customer if payment is SUCCESSFUL
    if ($isSuccess) {
        if (!$customerId && ($customerEmail || $customerPhone)) {
            // Create new customer - email is used as username
            $newCustomerName = $customerName ?: 'Customer ' . substr($transactionId ?: uniqid(), -6);
            $newEmail = $customerEmail ?: 'customer_' . time() . '@phidtech.com';
            $newPassword = $customerPasswordHash ?: password_hash(uniqid() . time(), PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("
                INSERT INTO users (name, email, phone, password, role, status, total_amount, paid_amount, created_at) 
                VALUES (?, ?, ?, ?, 'customer', 'active', ?, ?, NOW())
            ");
            $stmt->execute([
                $newCustomerName, 
                $newEmail, 
                $customerPhone, 
                $newPassword, 
                $amount, 
                $amount
            ]);
            $customerId = $conn->lastInsertId();
            
            file_put_contents($logFile, "[$timestamp] SUCCESS: Created NEW customer ID=$customerId, Name=$newCustomerName, Email=$newEmail (login username), Amount=$amount\n", FILE_APPEND);
        } elseif ($customerId) {
            // Update existing customer's payment totals
            $stmt = $conn->prepare("
                UPDATE users 
                SET paid_amount = COALESCE(paid_amount, 0) + ?, 
                    total_amount = COALESCE(total_amount, 0) + ? 
                WHERE id = ?
            ");
            $stmt->execute([$amount, $amount, $customerId]);
            file_put_contents($logFile, "[$timestamp] SUCCESS: Updated customer ID=$customerId with amount=$amount\n", FILE_APPEND);
        }
        
        // Update project if we have one
        if ($project) {
            $newPaidAmount = $project['paid_amount'] + $amount;
            $newStatus = ($newPaidAmount >= $project['total_amount']) ? 'payment_received' : $project['status'];
            
            $stmt = $conn->prepare("
                UPDATE projects 
                SET customer_id = COALESCE(customer_id, ?), 
                    paid_amount = ?, 
                    status = ? 
                WHERE id = ?
            ");
            $stmt->execute([$customerId, $newPaidAmount, $newStatus, $project['id']]);
            file_put_contents($logFile, "[$timestamp] Updated project ID={$project['id']}: paid_amount=$newPaidAmount, status=$newStatus\n", FILE_APPEND);
        }
        
        // Update pending payment status
        if ($pendingPayment) {
            $stmt = $conn->prepare("UPDATE pending_payments SET status = 'completed', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$pendingPayment['id']]);
        }
    } else {
        // Payment failed - update pending payment
        if ($pendingPayment) {
            $stmt = $conn->prepare("UPDATE pending_payments SET status = 'failed', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$pendingPayment['id']]);
        }
        file_put_contents($logFile, "[$timestamp] FAILED: Payment failed, status=$status\n", FILE_APPEND);
    }
    
    // Record the payment in revenue table (both success and failed for tracking)
    $stmt = $conn->prepare("
        INSERT INTO revenue (customer_id, description, amount, category, payment_method, transaction_reference, payment_status, date, recorded_by)
        VALUES (?, ?, ?, ?, 'Selcom', ?, ?, ?, NULL)
    ");
    $category = $isSuccess ? 'Selcom Payment' : 'Failed Payment';
    $stmt->execute([$customerId, $description, $amount, $category, $transactionId, $paymentStatus, $paymentDate]);
    $revenueId = $conn->lastInsertId();
    
    file_put_contents($logFile, "[$timestamp] Revenue recorded: ID=$revenueId, Category=$category, Amount=$amount\n", FILE_APPEND);
    
    // Send notifications for successful payments
    if ($isSuccess) {
        try {
            $notifier = new NotificationService($conn);
            
            // Notify customer about their payment
            if ($customerId) {
                $customerSubject = "Payment Confirmed - " . ($project ? $project['reference'] : $orderId);
                $customerMessage = "Your payment of TZS " . number_format($amount, 2) . " has been received successfully via Selcom. ";
                if ($project) {
                    $customerMessage .= "Project: {$project['title']}. ";
                }
                $customerMessage .= "Transaction ID: $transactionId. Thank you for your payment!";
                $notifier->send($customerId, $customerSubject, $customerMessage, 'payment', 'payment', $revenueId);
                file_put_contents($logFile, "[$timestamp] Notification sent to customer ID=$customerId\n", FILE_APPEND);
            }
            
            // Notify CEO, Manager, Accountant about payment received
            $staffSubject = "Payment Received - " . ($project ? $project['reference'] : $orderId);
            $staffMessage = "Payment of TZS " . number_format($amount, 2) . " received via Selcom. ";
            $staffMessage .= "Customer: " . ($customerName ?: 'Unknown') . " ($customerEmail). ";
            if ($project) {
                $staffMessage .= "Project: {$project['title']}. ";
            }
            $staffMessage .= "Transaction: $transactionId";
            $notifier->sendToRoles([ROLE_CEO, ROLE_MANAGER, ROLE_ACCOUNTANT], $staffSubject, $staffMessage, 'payment', 'payment', $revenueId);
            file_put_contents($logFile, "[$timestamp] Notification sent to CEO, Manager, Accountant\n", FILE_APPEND);
            
        } catch (Exception $e) {
            file_put_contents($logFile, "[$timestamp] Notification error: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }
    
    file_put_contents($logFile, "[$timestamp] ========== WEBHOOK COMPLETE ==========\n\n", FILE_APPEND);
    
    echo json_encode([
        'status' => $isSuccess ? 'success' : 'noted',
        'message' => $isSuccess ? 'Payment recorded successfully' : 'Failed payment recorded',
        'revenue_id' => $revenueId,
        'customer_id' => $customerId,
        'payment_status' => $paymentStatus
    ]);
    
} catch (PDOException $e) {
    file_put_contents($logFile, "[$timestamp] DATABASE ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
} catch (Exception $e) {
    file_put_contents($logFile, "[$timestamp] ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error']);
}
