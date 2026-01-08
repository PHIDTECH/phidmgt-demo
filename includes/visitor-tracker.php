<?php
/**
 * Visitor Tracking System
 * Logs page visits with IP, location, and other metrics
 */

// Prevent multiple inclusions
if (defined('VISITOR_TRACKER_LOADED')) {
    return;
}
define('VISITOR_TRACKER_LOADED', true);

// Define helper functions outside of any block
if (!function_exists('vt_getVisitorIP')) {
    function vt_getVisitorIP() {
        $ip = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
}

if (!function_exists('vt_getLocationFromIP')) {
    function vt_getLocationFromIP($ip) {
        $location = [
            'country' => 'Unknown',
            'country_code' => 'XX',
            'city' => 'Unknown',
            'region' => 'Unknown',
            'latitude' => 0,
            'longitude' => 0,
            'timezone' => 'UTC',
            'isp' => 'Unknown'
        ];
        
        // Skip for localhost
        if ($ip == '127.0.0.1' || $ip == '::1' || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0) {
            $location['country'] = 'Local';
            $location['city'] = 'Localhost';
            return $location;
        }
        
        // Use ip-api.com (free, no API key needed)
        $url = "http://ip-api.com/json/{$ip}?fields=status,country,countryCode,regionName,city,lat,lon,timezone,isp";
        
        $context = stream_context_create([
            'http' => ['timeout' => 2]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response) {
            $data = json_decode($response, true);
            if ($data && isset($data['status']) && $data['status'] === 'success') {
                $location['country'] = $data['country'] ?? 'Unknown';
                $location['country_code'] = $data['countryCode'] ?? 'XX';
                $location['city'] = $data['city'] ?? 'Unknown';
                $location['region'] = $data['regionName'] ?? 'Unknown';
                $location['latitude'] = $data['lat'] ?? 0;
                $location['longitude'] = $data['lon'] ?? 0;
                $location['timezone'] = $data['timezone'] ?? 'UTC';
                $location['isp'] = $data['isp'] ?? 'Unknown';
            }
        }
        
        return $location;
    }
}

if (!function_exists('vt_getDeviceType')) {
    function vt_getDeviceType($userAgent) {
        if (preg_match('/mobile|android|iphone|ipod|blackberry|opera mini|iemobile/i', $userAgent)) {
            return 'Mobile';
        } elseif (preg_match('/tablet|ipad/i', $userAgent)) {
            return 'Tablet';
        }
        return 'Desktop';
    }
}

if (!function_exists('vt_getBrowser')) {
    function vt_getBrowser($userAgent) {
        if (preg_match('/MSIE|Trident/i', $userAgent)) return 'Internet Explorer';
        if (preg_match('/Edge/i', $userAgent)) return 'Edge';
        if (preg_match('/Firefox/i', $userAgent)) return 'Firefox';
        if (preg_match('/Chrome/i', $userAgent)) return 'Chrome';
        if (preg_match('/Safari/i', $userAgent)) return 'Safari';
        if (preg_match('/Opera|OPR/i', $userAgent)) return 'Opera';
        return 'Other';
    }
}

if (!function_exists('vt_getOS')) {
    function vt_getOS($userAgent) {
        if (preg_match('/Windows NT 10/i', $userAgent)) return 'Windows 10';
        if (preg_match('/Windows NT 6.3/i', $userAgent)) return 'Windows 8.1';
        if (preg_match('/Windows/i', $userAgent)) return 'Windows';
        if (preg_match('/Mac OS X/i', $userAgent)) return 'macOS';
        if (preg_match('/Linux/i', $userAgent)) return 'Linux';
        if (preg_match('/Android/i', $userAgent)) return 'Android';
        if (preg_match('/iPhone|iPad/i', $userAgent)) return 'iOS';
        return 'Other';
    }
}

// Only track if database connection exists
if (isset($conn)) {
    try {
        // Create table if not exists
        $conn->exec("
            CREATE TABLE IF NOT EXISTS visitor_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45),
                country VARCHAR(100),
                country_code VARCHAR(10),
                city VARCHAR(100),
                region VARCHAR(100),
                latitude DECIMAL(10,8),
                longitude DECIMAL(11,8),
                timezone VARCHAR(50),
                isp VARCHAR(255),
                page_url VARCHAR(500),
                page_title VARCHAR(255),
                referrer VARCHAR(500),
                user_agent TEXT,
                device_type VARCHAR(50),
                browser VARCHAR(100),
                os VARCHAR(100),
                session_id VARCHAR(100),
                user_id INT DEFAULT NULL,
                visit_date DATE,
                visit_time TIME,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ip (ip_address),
                INDEX idx_date (visit_date),
                INDEX idx_country (country_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Log the visit
        $ip = vt_getVisitorIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $pageUrl = $_SERVER['REQUEST_URI'] ?? '/';
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $sessionId = session_id();
        
        // Check if this page was already logged in this session
        $sessionKey = 'visited_' . md5($pageUrl);
        $lastVisit = $_SESSION[$sessionKey] ?? 0;
        
        // Only log if more than 30 seconds since last visit to same page
        if (time() - $lastVisit > 30) {
            $_SESSION[$sessionKey] = time();
            
            // Get location data
            $location = vt_getLocationFromIP($ip);
            
            // Insert visitor log
            $stmt = $conn->prepare("
                INSERT INTO visitor_logs (
                    ip_address, country, country_code, city, region, 
                    latitude, longitude, timezone, isp,
                    page_url, page_title, referrer, user_agent,
                    device_type, browser, os, session_id, user_id,
                    visit_date, visit_time
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME())
            ");
            
            $stmt->execute([
                $ip,
                $location['country'],
                $location['country_code'],
                $location['city'],
                $location['region'],
                $location['latitude'],
                $location['longitude'],
                $location['timezone'],
                $location['isp'],
                $pageUrl,
                $pageTitle ?? 'PhidTech',
                $referrer,
                $userAgent,
                vt_getDeviceType($userAgent),
                vt_getBrowser($userAgent),
                vt_getOS($userAgent),
                $sessionId,
                $_SESSION['user_id'] ?? null
            ]);
        }
    } catch (Exception $e) {
        // Silently fail - don't break the page
    }
}
