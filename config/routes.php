<?php

use App\App;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

// Chốt chặn bảo mật: Cấm truy cập trực tiếp file này từ trình duyệt
if (!defined('EXECUTION_ALLOWED')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
    echo '<h1>403 Forbidden</h1><p>Direct access to this script is forbidden.</p>';
    exit;
}

/**
 * ==============================================================================
 * 🗺️ BẢN ĐỒ ĐỊNH TUYẾN TỔNG THỂ (GLOBAL ROUTES MAP) - VIETADVISOR.NET
 * ==============================================================================
 */

// 1. Phân tích chuỗi danh sách các Module Giao diện được cấu hình từ tệp .env
$webModulesString = $_ENV['WEB_MODULES'] ?? '';
$webModules = !empty($webModulesString) ? explode(',', $webModulesString) : [];

// 2. Khởi tạo phân hệ quản lý danh sách đen (Blacklist Cache) cho các file lỗi
// Thư mục lưu cache tạm đặt ngay tại vùng compile của Smarty để đảm bảo quyền ghi (Write Permission)
$cache = new FilesystemAdapter('failed_routes_cache', 0, ROOT_DIR . '/templates/templates_c');

// Lấy danh sách các tệp định tuyến bị lỗi từ request trước (nếu có)
$failedRoutes = $cache->get('blacklisted_files', function () {
    return [];
});

// 3. Thực hiện vòng lặp nạp thẳng siêu tốc các file định tuyến con của từng Module
foreach ($webModules as $module) {
    $moduleName = trim($module);
    $routeFile = ROOT_DIR . "/src/Modules/{$moduleName}/routes.php";

    // TIÊU CHUẨN TỐI ƯU: Nếu file nằm trong danh sách đen, bỏ qua lập tức không đọc ổ cứng
    if (isset($failedRoutes[$routeFile])) {
        continue;
    }

    // Thực hiện nạp thẳng siêu tốc không qua hàm kiểm tra vật lý is_file()
    try {
        require_once $routeFile;
    } catch (\Throwable $e) {
        // Nếu phát hiện lỗi (Thiếu file routes.php hoặc file có lỗi cú pháp PHP)
        $failedRoutes[$routeFile] = true;

        // Đóng băng tệp lỗi này vào Cache hệ thống để các request sau tự động né ra
        $cache->put('blacklisted_files', $failedRoutes);
    }
}


// Chốt chặn cuối cùng siêu tinh gọn trong file config/routes.php
App::router()->set404(function () {
    // ErrorHandler hiện tại đã tự động lo liệu 100% phần ngôn ngữ ngầm bên trong
    \App\Security\ErrorHandler::renderErrorPage(404, 'Not Found');
});

// Dòng lệnh test hệ thống (Chỉ chạy được khi .env đặt là development)
App::router()->get('/system-infrastructure-check-tool', 'App\Security\SystemCheck@environmentReport');
