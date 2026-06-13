<?php

namespace App\Security;

use App\App;

if (!defined('EXECUTION_ALLOWED')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
    echo '<h1>403 Forbidden</h1><p>Direct access to this script is forbidden.</p>';
    exit;
}

class SessionGuard
{
    /**
     * Khởi tạo, cấu hình hệ thống Session động từ .env và xử lý bẫy lỗi khóa cứng của server
     */
    public static function initialize(): void
    {
        $sessionHandler = $_ENV['SESSION_HANDLER'] ?? 'files';
        $sessionSavePath = $_ENV['SESSION_PATH'] ?? '';
        $isSessionConfigApplied = true;

        if ($sessionHandler === 'files') {
            if (empty($sessionSavePath)) {
                $sessionSavePath = ROOT_DIR . '/templates/templates_c/sessions';
            }
            if (!is_dir($sessionSavePath)) {
                @mkdir($sessionSavePath, 0700, true);
            }
            if (@ini_set('session.save_handler', 'files') === false || @ini_set('session.save_path', $sessionSavePath) === false) {
                $isSessionConfigApplied = false;
            }
        } else {
            if (@ini_set('session.save_handler', $sessionHandler) === false || @ini_set('session.save_path', $sessionSavePath) === false) {
                $isSessionConfigApplied = false;
            }
        }

        if (@ini_set('session.cookie_domain', '.' . ($_ENV['APP_DOMAIN'] ?? 'vietadvisor.test')) === false) {
            $isSessionConfigApplied = false;
        }

        @ini_set('session.cookie_httponly', 1);
        @ini_set('session.use_only_cookies', 1);
        @ini_set('session.cookie_samesite', 'Lax');

        $appConfig = require ROOT_DIR . '/config/app.php';
        if (@session_name($appConfig['security']['session_name']) === false) {
            $isSessionConfigApplied = false;
        }

        try {
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
        } catch (\Throwable $e) {
            @ini_restore('session.save_handler');
            @ini_restore('session.save_path');
            @ini_restore('session.cookie_domain');
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
        }

        App::bind('session_status_applied', (object)['success' => $isSessionConfigApplied]);
    }

    /**
     * Khóa dấu vân tay trình duyệt chống cướp phiên (Anti-Session Hijacking)
     */
    public static function validateFingerprint(): void
    {
        $request = App::request();
        $currentUserAgent = $request->headers->get('User-Agent', '');
        $currentIp        = $request->getClientIp();

        if (empty($currentIp) || filter_var($currentIp, FILTER_VALIDATE_IP) === false) {
            \App\Security\ErrorHandler::renderErrorPage(403, 'Forbidden');
        }

        if (empty($currentUserAgent)) {
            \App\Security\ErrorHandler::renderErrorPage(403, 'Forbidden');
        }

        if (!isset($_SESSION['va_fingerprint'])) {
            $_SESSION['va_fingerprint'] = [
                'user_agent' => $currentUserAgent,
                'ip'         => $currentIp
            ];
        } else {
            $savedFingerprint = $_SESSION['va_fingerprint'];

            if ($savedFingerprint['user_agent'] !== $currentUserAgent || $savedFingerprint['ip'] !== $currentIp) {
                $_SESSION = [];
                if (ini_get("session.use_cookies")) {
                    $params = session_get_cookie_params();
                    setcookie(
                        session_name(),
                        '',
                        time() - 42000,
                        $params["path"],
                        $params["domain"],
                        $params["secure"],
                        $params["httponly"]
                    );
                }
                session_destroy();
                \App\Security\ErrorHandler::renderErrorPage(403, 'Forbidden');
            }
        }
    }

    /**
     * Vòng phòng thủ nâng cao: Phát hiện và chặn đứng các kết nối Open Proxy lậu độc hại
     * Đmanager: Đã tối ưu hóa giảm tỷ lệ chặn nhầm (False Positives) đối với người dùng tốt
     */
    public static function validateProxy(): void
    {
        if (($_ENV['BLOCK_MALICIOUS_PROXY'] ?? '0') !== '1') {
            return;
        }

        // CHỈ QUÉT CÁC HEADERS ĐẶC TRƯNG CỦA CÔNG CỤ HACK VÀ PROXY LẬU ẨN DANH THÔ SƠ
        // Loại bỏ HTTP_VIA và HTTP_FORWARDED vì các proxy doanh nghiệp rất hay dùng 2 biến này
        $maliciousProxyHeaders = [
            'HTTP_USERAGENT_VIA',
            'HTTP_X_PROXY_ID',
            'HTTP_MAX_FORWARDS'
        ];

        foreach ($maliciousProxyHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                \App\Security\ErrorHandler::renderErrorPage(403, 'Forbidden');
            }
        }

        // CHỐT CHẶN BẢO VỆ CAO CẤP: Chống spam bằng cách chặn các Proxy khai báo thiếu User-Agent
        // (99% người dùng tốt qua proxy xịn đều có User-Agent đầy đủ từ trình duyệt)
        $request = App::request();
        $userAgent = $request->headers->get('User-Agent', '');

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) && empty($userAgent)) {
            // Có dấu hiệu Forward IP nhưng không có trình duyệt -> 100% là script hack tự động
            \App\Security\ErrorHandler::renderErrorPage(403, 'Forbidden');
        }
    }
}
