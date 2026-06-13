<?php
namespace App\Security;

use App\App;
use WhichBrowser\Parser;

// Chốt chặn bảo mật tầng file
if (!defined('EXECUTION_ALLOWED')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
    echo '<h1>403 Forbidden</h1><p>Direct access to this script is forbidden.</p>';
    exit;
}

class BrowserDetector {
    /**
     * Bóc tách toàn diện thông tin thiết bị, hệ điều hành và trình duyệt của khách
     * ĐÃ VÁ LỖI CẤU TRÚC: Phòng thủ tuyệt đối lỗi thuộc tính không định nghĩa (Undefined Property)
     * @return object Đối tượng chứa dữ liệu sạch đã được chuẩn hóa
     */
    public static function detect(): object {
        $userAgent = App::request()->headers->get('User-Agent', '');
        
        // Khởi chạy bộ phân tích của WhichBrowser
        $result = new Parser($userAgent);

        // 1. Kiểm tra an toàn sự tồn tại của thực thể Version trước khi trích xuất
        $browserVersion = '';
        if (isset($result->browser->version) && is_object($result->browser->version)) {
            $browserVersion = method_exists($result->browser->version, 'toString') 
                ? $result->browser->version->toString() 
                : (string)$result->browser->version;
        }

        // 2. 🔥 CHỐT CHẶN PHÒNG THỦ: Kiểm tra an toàn tuyệt đối cho thực thể OS Version
        $osVersion = '';
        if (isset($result->os->version) && is_object($result->os->version)) {
            $osVersion = method_exists($result->os->version, 'toString') 
                ? $result->os->version->toString() 
                : (string)$result->os->version;
        }

        return (object)[
            // Thông tin Trình duyệt
            'browser_name'    => $result->browser->name ?? 'Unknown Browser',
            'browser_version' => $browserVersion,

            // Thông tin Hệ điều hành
            'os_name'         => $result->os->name ?? 'Unknown OS',
            'os_version'      => $osVersion,

            // Phân loại Thiết bị phần cứng
            'device_type'     => $result->device->type ?? 'desktop'
        ];
    }
}
