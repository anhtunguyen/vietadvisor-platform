<?php
/**
 * ==============================================================================
 * 🚀 MODULE AUTH SUB-ROUTING - VIETADVISOR PLATFORM SYSTEM (FIXED BRAMUS)
 * ==============================================================================
 */

use App\App;

// ĐỒNG BỘ CÚ PHÁP BRAMUS ROUTER: Chuyển sang chuỗi kết nối sử dụng ký tự @
$controllerClass = 'App\Modules\Auth\Controllers\AuthController';

// Cổng vào giao diện form Đăng nhập
App::router()->get('auth/login', "{$controllerClass}@showLoginForm");

// Cổng tiếp nhận xử lý dữ liệu form gửi lên (POST) bọc khiên chống Flood
App::router()->post('auth/login', "{$controllerClass}@handleLogin");

// Cổng kích nổ lệnh Đăng xuất, hủy diệt phiên làm việc và bóc Cookie
App::router()->get('auth/logout', "{$controllerClass}@handleLogout");
