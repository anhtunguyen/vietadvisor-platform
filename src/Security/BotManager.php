<?php

namespace App\Security;

use App\App;

// Chốt chặn bảo mật tầng file: Cấm truy cập trực tiếp file từ trình duyệt
if (!defined('EXECUTION_ALLOWED')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
    echo '<h1>403 Forbidden</h1><p>Direct access to this script is forbidden.</p>';
    exit;
}

class BotManager
{
    /**
     * 🔥 CHỐT CHẶN CỬA NGÕ TỰ ĐỘNG (AUTOMATED FIREWALL TRIGGER)
     * Đóng gói toàn bộ logic bóc tách URI, kiểm quyền và tự động bẫy lỗi 403/404 tung hỏa mù.
     * Giúp file public/index.php sạch sẽ, không bị phình to mã nguồn nghiệp vụ chi tiết.
     */
    public static function validate(): void
    {
        // 1. Tính toán thông minh đường dẫn Admin động từ tệp tin cấu hình .env thông qua app.php
        $configApp  = require ROOT_DIR . '/config/app.php';
        $adminPath  = $configApp['admin_path'] ?? 'admin';
        $currentUri = trim(App::request()->getRequestUri(), '/');

        // 2. Kích nổ lệnh kiểm duyệt logic phân vùng hệ thống
        if (!self::checkAccess()) {

            // 🕵️ CHIẾN THUẬT TUNG HỎA MÙ ĐÃ ĐƯỢC ĐÓNG GÓI KÍN:
            // Nếu Bot xấu cố tình mò vào phân khu admin bảo mật, ném lỗi 404 Not Found giả định để lừa đảo hành vi
            if (str_starts_with($currentUri, $adminPath . '/') || $currentUri === $adminPath) {
                \App\Security\ErrorHandler::renderErrorPage(404, 'Not Found');
            } else {
                // Toàn bộ các phân khu còn lại bên ngoài, chặn cứng bằng 403 Forbidden để tiết kiệm băng thông máy chủ
                \App\Security\ErrorHandler::renderErrorPage(403, 'Forbidden');
            }
        }
    }

    /**
     * Kiểm tra quyền truy cập của Bot dựa theo phân vùng hệ thống
     *
     * @param string|null $zone Phân vùng truy cập (public, api, admin, hoặc null để tự nhận diện)
     * @return bool Trả về true nếu được phép truy cập, false nếu bị chặn
     */
    public static function checkAccess(?string $zone = null): bool
    {
        $request = App::request();

        // 1. Thu thập thông tin định danh Request từ phía khách hàng gửi lên
        $userAgent = $request->headers->get('User-Agent', '');
        $ip = $request->getClientIp();

        // Nếu Request không có chuỗi User-Agent, coi là Bot xấu và chặn ngay lập tức
        if (empty($userAgent)) {
            return false;
        }

        // 2. NẠP HẠ TẦNG CẤU HÌNH ĐỘNG TỪ FILE APP.PHP ĐỂ TRÍCH XUẤT ADMIN PATH TỪ .ENV
        $configApp = require ROOT_DIR . '/config/app.php';
        $adminPath = $configApp['admin_path'] ?? 'admin';

        // Tự động phân tách và định hình Phân vùng (Zone) từ Request URI bám sát cấu hình động .env
        if ($zone === null) {
            $currentPath = trim($request->getRequestUri(), '/');
            $zone = 'public'; // Phân vùng mặc định ngoài mặt tiền

            if (str_starts_with($currentPath, 'api/')) {
                $zone = 'api';
            } elseif (str_starts_with($currentPath, $adminPath . '/') || $currentPath === $adminPath) {
                $zone = 'admin';
            }
        }

        // 3. ĐỌC HẠ TẦNG CẤU HÌNH ĐỘNG TỪ FILE BOTS.JSON MỘT CỬA
        $botFile = ROOT_DIR . '/config/bots.json';
        $config = [];

        if (is_file($botFile)) {
            $jsonContent = @file_get_contents($botFile);
            if (!empty($jsonContent)) {
                $config = json_decode($jsonContent, true) ?? [];
            }
        }

        // Nếu file trống hoặc lỗi cấu trúc JSON, cho qua (Mở cửa dự phòng để tránh sập web lây lan)
        if (empty($config) || !isset($config['categories'])) {
            return true;
        }

        // 4. Phân loại danh tính Bot dựa trên chuỗi User-Agent (Vòng lặp tối ưu 2 vòng của bạn)
        $detectedType = null;
        $matchedBotName = null;

        foreach ($config['categories'] as $type => $bots) {
            foreach ($bots as $bot) {
                if (stripos($userAgent, $bot) !== false) {
                    $detectedType = $type;
                    $matchedBotName = $bot;
                    break 2; // Khớp danh tính -> Thoát cả 2 vòng lặp để tối ưu hiệu năng
                }
            }
        }

        // ==============================================================================
        // 🛡️ LỚP PHÒNG THỦ NÂNG CAO: PHÁT HIỆN SCRIPT LẬU GIẢ MẠO TRÌNH DUYỆT (BROWSER SPOOFING)
        // ==============================================================================
        // Nếu $detectedType === null, nghĩa là Request không khớp bất kỳ Bot nào trong bots.json
        // Hệ thống đang tạm coi request này là Trình duyệt của Người dùng thông thường lướt web.
        if ($detectedType === null) {
            if (App::has('client_info')) {
                $client = App::get('client_info');

                // Chốt chặn A: Bẫy chuỗi User-Agent dị biệt, rác hoặc hacker tự chế tạo làm gãy bộ đọc
                if ($client->browser_name === 'Unknown Browser' || $client->os_name === 'Unknown OS') {
                    return false;
                }

                // Chốt chặn B: Bẫy các script CLI cố tình khai báo User-Agent giả lập Chrome/Safari
                if (in_array($client->browser_name, ['Chrome', 'Safari', 'Firefox', 'Edge'], true)) {
                    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
                    $encoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';

                    // Trình duyệt thật luôn gửi kèm các Header thiết lập đồ họa và nén băng thông này
                    if (empty($accept) || empty($encoding)) {
                        return false; // 100% là Bot lậu giả lập trình duyệt để cào trộm dữ liệu
                    }
                }
            }

            return true; // Hoàn toàn vượt qua kiểm duyệt, là người dùng thật 100%
        }

        // 5. VÒNG PHÒNG THỦ SÂU: Xác thực Reverse DNS chống giả mạo danh tính Bot tốt
        if ($detectedType === 'good') {
            $isFakeBot = true;

            // Kiểm tra xem Bot tốt này thuộc hệ sinh thái nào để bốc tập danh sách trắng domain tương ứng
            if ($matchedBotName === 'Googlebot' && isset($config['whitelist']['google'])) {
                $isFakeBot = !self::verifyReverseDNS($ip, $config['whitelist']['google']);
            } elseif ($matchedBotName === 'Bingbot' && isset($config['whitelist']['bing'])) {
                $isFakeBot = !self::verifyReverseDNS($ip, $config['whitelist']['bing']);
            } elseif ($matchedBotName === 'YandexBot' && isset($config['whitelist']['yandex'])) {
                $isFakeBot = !self::verifyReverseDNS($ip, $config['whitelist']['yandex']);
            } elseif ($matchedBotName === 'Baiduspider' && isset($config['whitelist']['baidu'])) {
                $isFakeBot = !self::verifyReverseDNS($ip, $config['whitelist']['baidu']);
            } else {
                // Đối với các Bot tốt nhỏ hơn không cấu hình whitelist riêng, tạm thời thả cho qua
                $isFakeBot = false;
            }

            // Nếu kết quả xác thực xác nhận đây là Bot giả mạo (Fake Bot) -> Chặn lập tức
            if ($isFakeBot) {
                return false;
            }
        }

        // 6. Đối chiếu phân quyền: Kiểm tra xem loại Bot này có được phép vào phân vùng ($zone) hiện tại không
        $allowedTypesInZone = $config['access_control'][$zone] ?? [];
        return in_array($detectedType, $allowedTypesInZone);
    }

    /**
     * Cơ chế xác thực Reverse DNS chuyên sâu hai chiều (Double Reverse DNS Lookup)
     * Tiêu chuẩn bảo mật tối cao được khuyến nghị chính thức bởi Google và Microsoft Search.
     */
    private static function verifyReverseDNS(string $ip, array $allowedDomains): bool
    {
        // Bước 1: Tìm tên miền (Hostname) tương ứng với địa chỉ IP hiện tại
        $hostname = gethostbyaddr($ip);

        // Nếu hàm trả về chính địa chỉ IP ban đầu nghĩa là IP này không có bản ghi PTR (Không hợp lệ)
        if ($hostname === $ip) {
            return false;
        }

        // Bước 2: Tìm ngược lại địa chỉ IP (Forward Lookup) từ Hostname vừa tìm được
        $checkIp = gethostbyname($hostname);

        // Điều kiện chí mạng: Địa chỉ IP truy vấn ngược lại bắt buộc phải trùng khớp 100% với IP gốc
        if ($checkIp !== $ip) {
            return false;
        }

        // Bước 3: Kiểm tra xem đuôi Hostname có thuộc danh sách tên miền hợp pháp không
        foreach ($allowedDomains as $domain) {
            if (str_ends_with($hostname, $domain)) {
                return true; // Hoàn toàn trùng khớp và hợp lệ
            }
        }

        return false;
    }
}
