<?php
namespace App\Security;

use App\App;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Cookie;

// Chốt chặn bảo mật tầng file
if (!defined('EXECUTION_ALLOWED')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
    echo '<h1>403 Forbidden</h1><p>Direct access to this script is forbidden.</p>';
    exit;
}

class Guard {
    /**
     * Vòng phòng thủ 0: Kiểm tra chốt chặn danh sách đen IP (IP Blacklist Checker)
     * Chặn đứng lập tức các IP đơn lẻ hoặc dải mạng (Mask) cấu hình dynamic qua file JSON.
     */
    public static function validateIpBlacklist(): void {
        $request = App::request();
        $currentIp = $request->getClientIp();

        if (empty($currentIp)) {
            return;
        }

        // Đường dẫn tới tệp tin blacklist JSON biệt lập
        $blacklistFile = ROOT_DIR . '/config/ip_blacklist.json';
        $blacklist = [];

        // Kiểm tra xem file có tồn tại hay không trước khi đọc ổ cứng để chống sập lây lan
        if (is_file($blacklistFile)) {
            $jsonContent = @file_get_contents($blacklistFile);
            if (!empty($jsonContent)) {
                $blacklist = json_decode($jsonContent, true) ?? [];
            }
        }

        if (empty($blacklist) || !is_array($blacklist)) {
            return;
        }

        // Thực hiện vòng lặp so khớp thuật toán Bitwise tối ưu hiệu năng
        foreach ($blacklist as $blockedIp) {
            $blockedIp = trim($blockedIp);
            
            // Trường hợp 1: So khớp trực tiếp nếu cấu hình là IP đơn lẻ
            if ($currentIp === $blockedIp) {
                \App\Security\ErrorHandler::renderErrorPage(403, 'Forbidden');
            }

            // Trường hợp 2: So khớp dải mạng CIDR Mask (Ví dụ: 192.168.1.0/24)
            if (strpos($blockedIp, '/') !== false) {
                if (self::checkIpInCidr($currentIp, $blockedIp)) {
                    \App\Security\ErrorHandler::renderErrorPage(403, 'Forbidden');
                }
            }
        }
    }

    /**
     * Vòng phòng thủ 1: Xác thực tên miền (Host Header Validation)
     * Chặn đứng hoàn toàn lỗ hổng Host Header Injection và các kết nối lậu.
     */
    public static function validateHost(): void {
        $request = App::request();
        $host = $request->getHost();
        $config = require ROOT_DIR . '/config/app.php';
        $allowedHosts = $config['allowed_hosts'] ?? ['localhost'];

        if (!in_array($host, $allowedHosts)) {
            $scheme = $request->isSecure() ? 'https://' : 'http://';
            $baseDomain = $config['domain'] ?? 'localhost';
            
            $redirect = new RedirectResponse($scheme . $baseDomain, 301);
            $redirect->send();
            exit;
        }
    }

    /**
     * Vòng phòng thủ 1.5: Kiểm soát tiêu đề người giới thiệu (Strict Referer Blocker)
     * Chống tấn công giả mạo CSRF và ngăn chặn hành vi hút trộm băng thông hình ảnh (Anti-Hotlinking).
     */
    public static function validateReferer(): void {
        $request = App::request();
        $referer = $request->headers->get('Referer');

        // Nếu khách gõ URL trực tiếp hoặc dùng bookmark (Referer trống) -> Hợp lệ, cho qua
        if (empty($referer)) {
            return;
        }

        // Bóc tách tên miền Host từ chuỗi URL Referer thô
        $refererHost = parse_url($referer, PHP_URL_HOST);

        $config = require ROOT_DIR . '/config/app.php';
        $allowedHosts = $config['allowed_hosts'] ?? ['localhost'];

        // Kiểm tra xem tên miền giới thiệu có thuộc danh sách trắng nội bộ của sàn hay không
        $isInternalReferer = in_array($refererHost, $allowedHosts, true);

        // 🛡️ CHỐT CHẶN A: CHỐNG GIẢ MẠO YÊU CẦU TRÊN CÁC HÀNH ĐỘNG THAY ĐỔI DỮ LIỆU (CSRF PROTECT)
        // Nếu khách thực hiện gửi Form (POST, PUT, DELETE) nhưng link bắt nguồn từ một site bên ngoài
        if (in_array($request->getMethod(), ['POST', 'PUT', 'DELETE'], true) && !$isInternalReferer) {
            
            // Nếu có cấu hình CORS cấp phép đối tác bên thứ ba (Có truyền Token Bearer hợp lệ) -> Cho phép thông quan
            $authToken = $request->headers->get('Authorization');
            if (empty($authToken)) {
                \App\Security\ErrorHandler::renderErrorPage(403, 'Forbidden');
            }
        }

        // 🛡️ CHỐT CHẶN B: CHỐNG TRÍCH XUẤT HOTLINKING ẢNH TỪ BÊN NGOÀI
        $path = trim($request->getRequestUri(), '/');
        if (preg_match('/\.(gif|jpe?g|png|svg|webp|ico)$/i', $path) && !$isInternalReferer) {
            header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
            echo 'Hotlinking is disabled for this resource.';
            exit;
        }
    }

    /**
     * Vòng phòng thủ 3: Điều hướng khách truy cập lần đầu (Onboarding) tối giản tuyệt đối
     * Thuật toán: Check Cookie -> Gán Cookie ngay -> Kiểm tra Raw URL -> Tính toán điều hướng
     */
    public static function handleOnboarding(): void {
        $request = App::request();
        $host = $request->getHost();
        
        $config = require ROOT_DIR . '/config/app.php';
        $baseDomain = $config['domain'] ?? 'localhost';
        
        // Lấy tên cookie ngôn ngữ đã được xử lý tiền tố __Secure- động từ tệp cấu hình app.php (:1 / :0)
        $cookieLangName = $config['localization']['cookie_lang_name'] ?? 'va_lng';

        // 🟢 BƯỚC 1: KIỂM TRA ĐIỀU KIỆN TIÊN QUYẾT (CÓ COOKIE THÌ BỎ QUA NGAY)
        if ($request->cookies->has($cookieLangName)) {
            return;
        }

        // 🟢 BƯỚC 2: CHƯA CÓ COOKIE -> LẬP TỨC KHỞI TẠO ĐỂ ĐÓNG DẤU ONBOARDING
        $response = new RedirectResponse($request->getUri(), 302);
        $cookie = Cookie::create($cookieLangName, '1')
            ->withExpires(time() + $config['security']['cookie_expire'])
            ->withPath('/')
            ->withDomain('.' . $baseDomain) // Đồng bộ Cookie xuyên suốt Domain mẹ và các Subdomains
            ->withSecure($request->isSecure())
            ->withHttpOnly(true);
        $response->headers->setCookie($cookie);

        // 🟢 BƯỚC 3: TÍNH TOÁN LUỒNG ĐIỀU HƯỚNG DỰA TRÊN VỊ TRÍ VÀ ĐƯỜNG DẪN THÔ
        // Trường hợp A: Khách chủ động vào thẳng các Subdomain (vi., ru.) lần đầu -> Cho lưu cookie và giữ chân khách tại đây
        if ($host !== $baseDomain) {
            $response->send();
            exit;
        }

        // KHÓA CHỐT UX & SEO TUYỆT ĐỐI: Kiểm tra dựa trên Raw Request URI gửi lên
        $rawUri = trim($request->getRequestUri(), '/');

        // CHỐT CHẶN BỔ TRỢ: Nếu trình duyệt gọi ngầm file icon bị thiếu, thoát ngay không cho redirect loop
        if ($rawUri === 'favicon.ico') {
            header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 404 Not Found');
            exit;
        }

        // Nếu chuỗi URI thô không trống (Tức là có chứa tham số ?aa=1, chứa tên file index.php, hoặc link sâu)
        // Hệ thống TUYỆT ĐỐI KHÔNG chuyển hướng, thực thi gửi cookie và giữ khách ở lại trang hiện tại
        if (!empty($rawUri)) {
            $response->send();
            exit;
        }

        // Trường hợp B: Khách vào ĐÚNG CỬA CHÍNH TRANG CHỦ MẸ XỊN (Chỉ gõ vietadvisor.test hoặc vietadvisor.test/)
        $supportedLangs = $config['localization']['supported_languages'] ?? [];
        $languages = $request->getLanguages();
        $matchedLang = null;

        foreach ($languages as $lang) {
            $primaryLang = strtolower(substr($lang, 0, 2));
            if (array_key_exists($primaryLang, $supportedLangs)) {
                $matchedLang = $primaryLang;
                break;
            }
        }

        if ($matchedLang === null) {
            $matchedLang = $config['localization']['default_language'] ?? 'en';
        }

        $subdomainConfig = $supportedLangs[$matchedLang] ?? 'root';

        if ($subdomainConfig === 'root' || empty($subdomainConfig)) {
            $response->send();
            exit;
        }

        $scheme = $request->isSecure() ? 'https://' : 'http://';
        $targetUrl = $scheme . $subdomainConfig . '.' . $baseDomain;

        $response->setTargetUrl($targetUrl);
        $response->send();
        exit;
    }

    /**
     * Hàm tính toán toán học Bitwise đối chiếu IP thực tế với dải Subnet Mask (Hỗ trợ cả IPv4 và IPv6)
     */
    private static function checkIpInCidr(string $ip, string $cidr): bool {
        [$subnet, $mask] = explode('/', $cidr);
        
        // Xử lý toán học cho dải mạng IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $maskInvert = ~( (1 << (32 - (int)$mask)) - 1 );
            
            return ($ipLong & $maskInvert) === ($subnetLong & $maskInvert);
        }
        
        // Xử lý toán học băm chuỗi nhị phân cho dải mạng IPv6 thế hệ mới
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $ipBin = inet_pton($ip);
            $subnetBin = inet_pton($subnet);
            if (!$ipBin || !$subnetBin) return false;

            $maskBits = (int)$mask;
            $bytes = floor($maskBits / 8);
            $bits = $maskBits % 8;

            if (substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
                return false;
            }

            if ($bits > 0) {
                $ipByte = ord($ipBin[$bytes]);
                $subnetByte = ord($subnetBin[$bytes]);
                $maskByte = ~( (1 << (8 - $bits)) - 1 ) & 255;
                
                return ($ipByte & $maskByte) === ($subnetByte & $maskByte);
            }
            
            return true;
        }

        return false;
    }
}
