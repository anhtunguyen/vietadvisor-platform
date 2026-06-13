<?php

use App\App;
use App\Security\BotManager;

// Chốt chặn bảo mật tầng file: Cấm truy cập trực tiếp từ trình duyệt
if (!defined('EXECUTION_ALLOWED')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
    echo '<h1>403 Forbidden</h1><p>Direct access to this script is forbidden.</p>';
    exit;
}

/**
 * ==============================================================================
 * 🏠 BẢN ĐỒ ĐỊNH TUYẾN MODULE TRANG CHỦ (HOME MODULE ROUTES) - VIETADVISOR.NET
 * ==============================================================================
 */

// Đăng ký tuyến đường Trang chủ (URL: /)
App::router()->get('/', function () {

    // 🛡️ VÒNG PHÒNG THỦ BẢO MẬT: Kiểm tra quyền truy cập của Bot tại vùng phân vùng công khai 'public'
    // Chặn đứng lập tức các loại Bot xấu (bad bots) hoặc các Hacker cố tình đổi tên User-Agent giả danh Googlebot
    if (!BotManager::checkAccess('public')) {
        // Gọi ErrorHandler trung tâm để render ra trang lỗi 403 đẹp mắt, đúng chuẩn HTTP
        \App\Security\ErrorHandler::renderErrorPage(403, 'Forbidden');
    }

    // Sau khi vượt qua tường bảo mật Bot, khởi tạo và kích hoạt Class xử lý logic đơn nhiệm của trang chủ
    // Cơ chế Autoloading PSR-4 của Composer sẽ tự động nạp ngầm file HomeController.php khi dòng này được chạy
    $controller = new \App\Modules\Home\Controllers\HomeController();
    $controller->index();
});
