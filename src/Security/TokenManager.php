<?php
namespace App\Security;

use Firebase\JWT\JWT;

if (!defined('EXECUTION_ALLOWED')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
    echo '<h1>403 Forbidden</h1><p>Direct access to this script is forbidden.</p>';
    exit;
}

class TokenManager {
    /**
     * Sinh chuỗi Token khóa chặt vào duy nhất một Domain và các Subdomain của nó
     * TokenManager::generatePartnerToken('partner.com')
     * @param string $allowedDomain Tên miền đối tác được cấp phép (Ví dụ: 'partner.com')
     * @param int $daysValid Số ngày token có hiệu lực
     */
    public static function generatePartnerToken(string $allowedDomain, int $daysValid = 365): string {
        $secretKey = $_ENV['APP_SECRET_KEY'] ?? 'defuse_crypto_hex_key_32_characters_minimum_here_';
        
        $issuedAt = time();
        $expireTime = $issuedAt + ($daysValid * 24 * 60 * 60);

        // Chuẩn hóa tên miền (Xóa http://, https:// và dấu / ở cuối nếu đối tác gõ thừa)
        $cleanDomain = preg_replace('#^https?://#', '', rtrim(trim($allowedDomain), '/'));

        // 🧬 TẠO BIỂU THỨC CHÍNH QUY (REGEX) ĐỂ KHÓA CHẶT TÊN MIỀN
        // Biểu thức này sẽ khớp với chính partner.com HOẶC mọi subdomain dạng *.partner.com
        $domainRegex = '^https?://(.*\.)?' . preg_quote($cleanDomain, '#') . '$';

        $payload = [
            'iss'  => 'VietAdvisor',
            'aud'  => $domainRegex,           // Khóa chặt chuỗi Regex bảo mật vào trường AUD
            'iat'  => $issuedAt,
            'exp'  => $expireTime,
            'purp' => 'cross_domain_api'
        ];

        return JWT::encode($payload, $secretKey, 'HS256');
    }
}
