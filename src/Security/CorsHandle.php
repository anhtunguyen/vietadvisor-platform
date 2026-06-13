<?php
namespace App\Security;

use App\App;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Chốt chặn bảo mật tầng file: Cấm truy cập trực tiếp file từ trình duyệt
if (!defined('EXECUTION_ALLOWED')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
    echo '<h1>403 Forbidden</h1><p>Direct access to this script is forbidden.</p>';
    exit;
}

class CorsHandle {
    /**
     * Thực thi chính sách hạn chế đa nguồn nghiêm ngặt kết hợp xác thực Token khóa chặt miền
     * Thuật toán: Nhận diện Origin -> Quét mảng Host nội bộ -> Giải mã JWT -> So khớp Regex Domain -> Đóng/Mở cổng CORS
     */
    public static function handle(): void {
        $request = App::request();
        $origin = $request->headers->get('Origin');

        // Nếu request không đi từ trình duyệt (Không chứa Header Origin) -> Hợp lệ, cho qua luôn
        if (empty($origin)) {
            return;
        }

        // ==============================================================================
        // 🔑 BƯỚC 1: BÓC TÁCH CHUỖI TOKEN BẢO MẬT TỪ HTTP HEADER GỬI LÊN
        // ==============================================================================
        $authToken = $request->headers->get('Authorization');
        
        // Fallback dự phòng lấy header cho một số cấu hình máy chủ FastCGI đặc thù
        if (empty($authToken) && function_exists('getallheaders')) {
            $headers = getallheaders();
            $authToken = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }

        // Tách bỏ từ khóa 'Bearer ' để lấy chuỗi mã hóa JWT thô tinh khiết
        $token = null;
        if (!empty($authToken) && preg_match('/Bearer\s(\S+)/', $authToken, $matches)) {
            $token = $matches[1];
        }

        // ==============================================================================
        // 🧬 BƯỚC 2: GIẢI MÃ JWT VÀ THỰC THI CHỐT CHẶN XÁC THỰC TÊN MIỀN ĐỐI TÁC
        // ==============================================================================
        $isTokenValid = false;
        
        if (!empty($token)) {
            try {
                // Lấy chiếc chìa khóa tối cao từ file cấu hình .env làm muối giải mã ký số
                $secretKey = $_ENV['APP_SECRET_KEY'] ?? 'defuse_crypto_hex_key_32_characters_minimum_here_';
                
                // Thực hiện giải mã Token (Nếu sai chữ ký số hoặc hết hạn, hàm sẽ tự ngắt nhảy xuống khối catch)
                $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
                
                // Kiểm tra mục đích sử dụng và xuất xứ bản quyền của Token
                if (($decoded->iss ?? '') === 'VietAdvisor' && ($decoded->purp ?? '') === 'cross_domain_api') {
                    
                    // Bốc chuỗi Regex khóa miền được mã hóa cứng trong lõi Token lúc sinh mã (Trường AUD)
                    $allowedDomainRegex = $decoded->aud ?? '';
                    
                    // Tiến hành so khớp Bitwise toán học chuỗi Regex này với tên miền Origin thực tế của khách
                    if (!empty($allowedDomainRegex) && preg_match('#' . $allowedDomainRegex . '#i', $origin)) {
                        $isTokenValid = true; // Token hoàn toàn trùng khớp và có hiệu lực với tên miền đang gọi!
                    }
                }
            } catch (\Exception $e) {
                $isTokenValid = false; // Token giả mạo, hết hạn hoặc sai chữ ký bảo mật
            }
        }

        // ==============================================================================
        // 🗺️ BƯỚC 3: ĐỐI CHIẾU DANH SÁCH TRẮNG NỘI BỘ (FALLBACK CHO HỆ SINH THÁI SUBDOMAINS)
        // ==============================================================================
        $config = require ROOT_DIR . '/config/app.php';
        $allowedHosts = $config['allowed_hosts'] ?? ['localhost'];
        $parsedOrigin = parse_url($origin, PHP_URL_HOST);
        
        // Trả về true nếu tên miền đang gọi Ajax là một Subdomain con chính thức của sàn
        $isInternalDomain = in_array($parsedOrigin, $allowedHosts, true);

        // ==============================================================================
        // 🎛️ BƯỚC 4: ĐIỀU PHỐI ĐÓNG MỞ CỬA CỔNG TRUYỀN TẢI CORS
        // ==============================================================================
        if ($isInternalDomain || $isTokenValid) {
            
            // Khai báo cấp quyền mở cửa thông quan cho nguồn đang yêu cầu dữ liệu
            header("Access-Control-Allow-Origin: " . $origin);
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
            header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
            
            // 🔒 BẢO VỆ TẦNG SÂU: Chỉ cho phép truyền Cookie Session đối với hệ thống Subdomain nội bộ của sàn.
            // Đối với các đối tác bên thứ ba ở ngoài, khóa chặt về 'false' để triệt tiêu lỗ hổng cướp Cookie tầng mạng.
            header("Access-Control-Allow-Credentials: " . ($isInternalDomain ? "true" : "false"));

            // Bẻ gãy nhanh và trả phản hồi sớm cho request kiểm tra trước OPTIONS (Preflight Request)
            if ($request->getMethod() === 'OPTIONS') {
                header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 204 No Content');
                exit;
            }
        } else {
            // Nếu không thuộc dải Subdomain xịn, cũng không chìa ra được Token khớp domain -> Cấm cửa lỗi 403 ngay lập tức
            \App\Security\ErrorHandler::renderErrorPage(403, 'Forbidden');
        }
    }
}
