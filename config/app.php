<?php

// Chốt chặn bảo mật: Cấm truy cập trực tiếp file này từ trình duyệt
if (!defined('EXECUTION_ALLOWED')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
    echo '<h1>403 Forbidden</h1><p>Direct access to this script is forbidden.</p>';
    exit;
}

/**
 * ==============================================================================
 * ⚙️ CẤU HÌNH ỨNG DỤNG TẬP TRUNG (GLOBAL APPLICATION SETTINGS) - VIETADVISOR.NET
 * ==============================================================================
 */

// 1. Kiểm tra môi trường hạ tầng mạng thực tế của Máy chủ
$isProduction = ($_ENV['APP_ENV'] ?? 'production') === 'production';
$isHttps = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === 1))
           || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
           || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

// Tiền tố bảo mật tối cao chỉ kích hoạt khi đủ điều kiện môi trường Production + HTTPS
$securePrefix = ($isProduction && $isHttps) ? '__Secure-' : '';

// 2. Hàm Điều phối Thông Minh: Tự động phân tách cấu trúc chuỗi "tên_cookie:cờ_hiệu" từ .env
$parseCookieConfig = function (?string $envValue, string $defaultName) use ($securePrefix): string {
    if (empty($envValue) || strpos($envValue, ':') === false) {
        return $defaultName;
    }

    [$cookieName, $secureFlag] = explode(':', $envValue);
    $cookieName = trim($cookieName);

    // Nếu cờ hiệu bằng 1 và máy chủ kích hoạt HTTPS -> Tự động gắn tiền tố bảo mật
    if (trim($secureFlag) === '1') {
        return $securePrefix . $cookieName;
    }

    return $cookieName;
};

return [
    /**
     * 1. Cấu hình Môi trường & Chế độ Debug (Environment Settings)
     */
    'env'   => $_ENV['APP_ENV'] ?? 'production',
    'debug' => !$isProduction,

    /**
     * 2. Thông tin Tên miền & Danh sách trắng Máy chủ (Domain Configuration)
     */
    'domain'        => $_ENV['APP_DOMAIN'] ?? 'vietadvisor.test',
    'allowed_hosts' => explode(',', $_ENV['ALLOWED_HOSTS'] ?? 'localhost'),
    // 🔥 BỔ SUNG DÒNG NÀY: Chuẩn hóa chuỗi đường dẫn admin từ file .env
    'admin_path'    => trim($_ENV['ADMIN_PATH'] ?? 'admin', '/'),

    /**
     * 3. Phân hệ Quản lý Ngôn ngữ & Điều hướng khách mới (Localization & Onboarding)
     */
    'localization' => [
        // Ngôn ngữ mặc định khi không khớp trình duyệt người dùng
        'default_language' => $_ENV['DEFAULT_LANGUAGE'] ?? 'en',

        // Nhóm A: Cookie Ngôn ngữ nhạy cảm (Cấu hình gốc từ .env mang cờ hiệu :1)
        'cookie_lang_name' => $parseCookieConfig($_ENV['COOKIE_LANG_NAME'] ?? null, 'va_lng'),

        // Mảng phân tách danh sách ngôn ngữ và subdomain tương ứng bóc tách tự động
        'supported_languages' => (function () {
            $langConfigString = $_ENV['SUPPORTED_LANGUAGES'] ?? '';
            $supportedLangs = [];
            if (!empty($langConfigString)) {
                $pairs = explode(',', $langConfigString);
                foreach ($pairs as $pair) {
                    if (strpos($pair, ':') !== false) {
                        [$langCode, $subdomain] = explode(':', $pair);
                        $supportedLangs[trim($langCode)] = trim($subdomain);
                    }
                }
            }
            return $supportedLangs;
        })()
    ],

    /**
     * 4. Phân hệ Quản lý Cookies & Khóa bảo mật (Security & Cookie Protocols)
     */
    'security' => [
        'secret_key'    => $_ENV['APP_SECRET_KEY'] ?? 'defuse_crypto_hex_key_32_characters_minimum_here_',
        'cookie_expire' => 31536000, // Zeit chuẩn sống của Cookie Onboarding và UX (1 năm)

        // Nhóm A: Tên Cookie định danh Session nhạy cảm (Cấu hình mang cờ hiệu :1 từ .env)
        'session_name'  => $parseCookieConfig($_ENV['COOKIE_SESSION_NAME'] ?? null, 'va_session'),

        // Nhóm B: Danh sách các Cookie tiện ích Front-end (Luôn giữ tên thô sạch do mang cờ hiệu :0)
        'cookies_ux' => [
            'theme'    => $parseCookieConfig($_ENV['COOKIE_THEME_NAME'] ?? null, 'va_theme'),
            'popup'    => $parseCookieConfig($_ENV['COOKIE_POPUP_NAME'] ?? null, 'va_popup'),
            'timezone' => $parseCookieConfig($_ENV['COOKIE_TIMEZONE_NAME'] ?? null, 'va_tz'), // Đồng bộ Cookie múi giờ động
        ]
    ],

    /**
     * 5. Cấu hình hệ thống Gửi Email tập trung (PHPMailer Service Settings)
     */
    'mail' => [
        'host'       => $_ENV['MAIL_HOST'] ?? 'smtp.mailtrap.io',
        'port'       => (int)($_ENV['MAIL_PORT'] ?? 2525),
        'username'   => $_ENV['MAIL_USERNAME'] ?? null,
        'password'   => $_ENV['MAIL_PASSWORD'] ?? null,
        'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
        'from_email' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@vietadvisor.com',
        'from_name'  => $_ENV['MAIL_FROM_NAME'] ?? 'VietAdvisor Platform',
    ],

    /**
     * 6. Cấu hình Tích hợp API mạng xã hội xác thực (Google OAuth2 API)
     */
    'oauth' => [
        'google' => [
            'client_id'     => $_ENV['GOOGLE_CLIENT_ID'] ?? '',
            'client_secret' => $_ENV['GOOGLE_CLIENT_SECRET'] ?? '',
            'redirect_url'  => $_ENV['GOOGLE_REDIRECT_URL'] ?? '',
        ]
    ]
];
