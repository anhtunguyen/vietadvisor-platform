<?php

namespace App\Security;

use App\App;
use App\Services\RememberMeService;

if (!defined('EXECUTION_ALLOWED')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
    exit;
}

class AccessGate
{
    /**
     * Kiểm duyệt quyền truy cập toàn sàn (Strict Access Gatekeeper)
     */
    public static function watch(): void
    {
        $request = App::request();
        $currentUri = trim($request->getRequestUri(), '/');

        // 1. Nạp bản đồ phân quyền động từ cấu hình JSON một cửa
        $permFile = ROOT_DIR . '/config/permissions.json';
        if (!is_file($permFile)) {
            return;
        }
        $perms = json_decode(file_get_contents($permFile), true) ?? [];

        // 2. NHẬN DIỆN VÙNG TRUY CẬP ĐỂ TỐI ƯU TRUY VẤN
        $configApp = require ROOT_DIR . '/config/app.php';
        $adminPath = $configApp['admin_path'] ?? 'admin';
        $isTargetingProtectedZone = false;
        $targetRules = [];

        if (isset($perms['zones'])) {
            foreach ($perms['zones'] as $zonePrefix => $rules) {
                $targetPrefix = ($zonePrefix === 'admin') ? $adminPath : $zonePrefix;
                if (str_starts_with($currentUri, $targetPrefix . '/') || $currentUri === $targetPrefix) {
                    $isTargetingProtectedZone = true;
                    $targetRules = $rules;
                    break;
                }
            }
        }

        // TỐI ƯU CPU: Nếu khách chỉ vào trang public thường, cho qua thẳng (Lazy Loading)
        if (!$isTargetingProtectedZone) {
            return;
        }

        // 3. 🔥 ĐÃ RÚT GỌN: Triệu gọi cổng dịch vụ dùng chung xử lý Đăng nhập tự động
        if (!isset($_SESSION['user_id'])) {
            RememberMeService::loginWithCookie();
        }

        // 4. BIỆN PHÁP XÁC THỰC HAI CHIỀU THẮT CHẶT ĐỐI CHIẾU LIVE DB
        $userRole = 'guest';
        $userId = $_SESSION['user_id'] ?? null;

        if ($userId !== null) {
            try {
                $capsule = App::get('db');
                $user = $capsule::table('users')->where('id', $userId)->first();

                if (!$user || $user->status !== 'active') {
                    self::destroySessionAndRedirect();
                }

                $currentFingerprint = md5($request->getClientIp() . $request->headers->get('User-Agent', ''));
                $savedFingerprint = $_SESSION['user_fingerprint'] ?? '';
                if ($currentFingerprint !== $savedFingerprint) {
                    self::destroySessionAndRedirect();
                }

                $userRole = $user->role;
            } catch (\Throwable $e) {
                $userRole = 'guest';
            }
        }

        // 5. ĐỐI CHIẾU PHÂN QUYỀN VÀ TRẢM QUYẾT ĐOÁN
        self::verifyRole($userRole, $targetRules, $currentUri, $adminPath);
    }

    private static function verifyRole(string $userRole, array $rules, string $currentUri, string $adminPath): void
    {
        $allowedRoles = $rules['allowed_roles'] ?? [];
        if (!in_array($userRole, $allowedRoles, true)) {
            $errorType = $rules['error_type'] ?? '403';
            if ($errorType === '404' || str_starts_with($currentUri, $adminPath . '/') || $currentUri === $adminPath) {
                \App\Security\ErrorHandler::renderErrorPage(404, 'Not Found');
            }
            if ($errorType === 'json') {
                header('Content-Type: application/json; charset=utf-8');
                header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
                echo json_encode(['success' => false, 'error' => 'Unauthenticated or Invalid privileges.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            \App\Security\ErrorHandler::renderErrorPage(403, 'Forbidden');
        }
    }

    private static function destroySessionAndRedirect(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }

        // 🔥 ĐÃ ĐỒNG BỘ: Ủy thác lệnh xóa Cookie qua lớp dịch vụ trung tâm dùng chung
        RememberMeService::clearCookie();

        \App\Security\ErrorHandler::renderErrorPage(403, 'Forbidden');
    }
}
