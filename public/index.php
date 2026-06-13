<?php
/**
 * ==============================================================================
 * 🚪 VIETADVISOR PLATFORM - BOOTSTRAP ENTRYPOINT (CỬA NGÕ DUY NHẤT HỆ THỐNG)
 * ==============================================================================
 * Định cấu hình môi trường, bảo mật phiên làm việc và kích hoạt bộ định tuyến.
 */

// 1. Tuyệt đối hóa đường dẫn vật lý: Thay thế toàn bộ dấu \ thành / để đồng bộ Windows/Linux
define('ROOT_DIR', str_replace('\\', '/', dirname(__DIR__)));

// 2. Định nghĩa hằng số bảo mật tối cao làm "chìa khóa cửa" cho các file require con
define('EXECUTION_ALLOWED', true);

// 3. Kích hoạt tính năng tự động nạp Class (Autoloading) của Composer
require ROOT_DIR . '/vendor/autoload.php';

// 4. KÍCH HOẠT HỆ THỐNG QUẢN LÝ LỖI TẬP TRUNG NGAY LẬP TỨC
\App\Security\ErrorHandler::register();

use App\App;
use App\Security\Guard;
use App\Security\SessionGuard;
use Symfony\Component\HttpFoundation\Request;
use Illuminate\Database\Capsule\Manager as Capsule;

// 5. NẠP BIẾN MÔI TRƯỜNG: Giải nén tệp .env vào bộ nhớ hệ thống trước tiên
\Dotenv\Dotenv::createImmutable(ROOT_DIR)->load();

// 6. CÔ LẬP LỖI PHÍA TRÌNH DUYỆT & THIẾT LẬP TIMEZONE NỀN TẢNG CỐ ĐỊNH
if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    error_reporting(0);
} else {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    ini_set('log_errors', 1);
    error_reporting(E_ALL);
}

// Cố định múi giờ cho tầng xử lý logic của PHP bám sát file cấu hình .env
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'UTC');

// ==============================================================================
// 🌐 7. KHỞI TẠO REQUEST & PHÂN TÍCH THIẾT BỊ KHÁCH (REQUEST LIFECYCLE)
// ==============================================================================
App::bind('request', Request::createFromGlobals());

// 🔥 BỔ SUNG DÒNG NÀY: Phân tích trình duyệt/HĐH đúng 1 lần duy nhất lúc đón khách
// Ép kiểu đối tượng (Object) để thỏa mãn cấu trúc thắt chặt Type của App::bind()
App::bind('client_info', \App\Security\BrowserDetector::detect());

// ==============================================================================
// 🌐 8. ĐỊNH NGHĨA BẢN ĐỒ URL HỆ THỐNG ĐA TÊN MIỀN ĐỘNG (ZERO EXTRA VARIABLES)
// ==============================================================================
// Định nghĩa ROOT_URL cho các liên kết định tuyến dynamic cục bộ
define('ROOT_URL', rtrim(App::request()->getSchemeAndHttpHost() . App::request()->getBasePath(), '/') . '/');

// Tự động nhận diện giao thức, xóa bỏ biến thừa, ép chạy qua Cookie-Free Domain từ .env
if (!empty($_ENV['APP_ASSETS_DOMAIN'])) {
    define(
        'ASSETS_URL',
        (App::request()->isSecure() ? 'https://' : 'http://') .
        $_ENV['APP_ASSETS_DOMAIN'] .
        rtrim(App::request()->getBasePath(), '/') . '/'
    );
} else {
    define('ASSETS_URL', ROOT_URL . 'assets/');
}

// ==============================================================================
// 🔒 9. KHỞI TẠO HỆ THỐNG SESSION VÀ COOKIE ĐỘNG (BÓC TÁCH TIN GỌN CHUYÊN TRÁCH)
// ==============================================================================
// Lớp SessionGuard tự động phân tách cờ hiệu :1 / :0 và xử lý bẫy lỗi khóa cứng máy chủ
SessionGuard::initialize();

// ==============================================================================
// 🛡️ 10. KÍCH HOẠT VÒNG PHÒNG THỦ MÁY CHỦ CHỦ ĐỘNG (MẠNG LƯỚI SONG SONG)
// ==============================================================================
// Lớp 0: Chặn đứng lập tức nếu IP nằm trong danh sách đen động từ file JSON (Quét siêu tốc)
Guard::validateIpBlacklist();

// Lớp 0.2: Cấp phép đa nguồn Subdomain và bẻ gãy Preflight Request OPTIONS (CORS Handle)
\App\Security\CorsHandle::handle();

// Lớp 0.3: Kiểm soát tiêu đề người giới thiệu, phòng chống Hotlinking và CSRF thô
Guard::validateReferer();

// Lớp 0.4: 🔥 ĐÃ KHỬ PHÌNH TO: Gọi lệnh gọn gàng, BotManager tự động bóc tách phân vùng bảo mật
\App\Security\BotManager::validate();

// Lớp 0.5: Quét dấu vết Open Proxy lậu độc hại (Cấu hình linh hoạt bật/tắt qua .env)
SessionGuard::validateProxy();

// Lớp 1: Xác thực Host Header chặn đứng lỗ hổng Host Header Injection
Guard::validateHost();

// Lớp 2: Khóa chốt định dạng IP và User-Agent chống cướp phiên (Anti-Session Hijacking)
SessionGuard::validateFingerprint();

// Lớp 3: Điều hướng Onboarding tối giản tuyệt đối dựa trên Cookie bảo mật (va_lng)
Guard::handleOnboarding();

// ==============================================================================
// 📊 11. KHỞI TẠO DATABASE ELOQUENT ORM
// ==============================================================================
App::bind('db', (function () {
    $db = new Capsule();

    // Nạp mảng cấu hình kết nối tập trung từ tệp chuyên trách config/database.php
    $config = require ROOT_DIR . '/config/database.php';
    $connection = $config['connections'][$config['default']] ?? [];

    $db->addConnection($connection);
    $db->setAsGlobal();
    $db->bootEloquent();
    return $db;
})());

// ==============================================================================
// 🎛️ 12. KHỞI CHẠY HỆ THỐNG ĐỊNH TUYẾN TẬP TRUNG (ROUTER PLATFORM)
// ==============================================================================
App::bind('router', new \Bramus\Router\Router());

// Nạp bản đồ tuyến đường tổng để tự động quét mảng WEB_MODULES bóc tách từ .env
require ROOT_DIR . '/config/routes.php';

// Thực thi tìm kiếm chính xác Class Controller con xử lý URL hiện tại của khách
App::router()->run();
