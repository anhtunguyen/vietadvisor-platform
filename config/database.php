<?php

// Chốt chặn bảo mật: Cấm truy cập trực tiếp file này từ trình duyệt
if (!defined('EXECUTION_ALLOWED')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
    echo '<h1>403 Forbidden</h1><p>Direct access to this script is forbidden.</p>';
    exit;
}

/**
 * ==============================================================================
 * 📊 CẤU HÌNH CƠ SỞ DỮ LIỆU ĐỒNG BỘ (DATABASE SETTINGS) - VIETADVISOR.NET
 * ==============================================================================
 */
return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver'    => $_ENV['DB_DRIVER'] ?? 'mysql',
            'host'      => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'port'      => $_ENV['DB_PORT'] ?? '3306',
            'database'  => $_ENV['DB_DATABASE'] ?? 'vietadvisor_db',
            'username'  => $_ENV['DB_USERNAME'] ?? 'root',
            'password'  => $_ENV['DB_PASSWORD'] ?? '',
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'strict'    => true,
            'engine'    => null,
            
            // 🔥 KHÓA CHỐT CHÍ MẠNG: Ép kết nối MySQL luôn dịch chuyển dữ liệu về múi giờ UTC-0
            'timezone'  => '+00:00', 
        ],
    ],
];
