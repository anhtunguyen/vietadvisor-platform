<?php
namespace App\Security;

use App\App;
use App\Services\RememberMeService;

if (!defined('EXECUTION_ALLOWED')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
    exit;
}

class AccessGate {
    /**
     * Màng lọc phân quyền phân rã đặc quyền đặc khu (Permission-Based Access Gatekeeper)
     */
    public static function watch(): void {
        $request = App::request();
        $currentUri = trim($request->getRequestUri(), '/');

        $permFile = ROOT_DIR . '/config/permissions.json';
        if (!is_file($permFile)) {
            return; 
        }
        $perms = json_decode(file_get_contents($permFile), true) ?? [];

        // 1. NHẬN DIỆN PHÂN VÙNG URL ĐỂ LẤY LUẬT
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

        if (!$isTargetingProtectedZone) {
            return;
        }

        if (!isset($_SESSION['user_id'])) {
            RememberMeService::loginWithCookie();
        }

        $userId = $_SESSION['user_id'] ?? null;
        $hasPrivilege = false;

        if ($userId !== null) {
            // 🔥 CHỐT CHẶN ĐẶC QUYỀN TỐI CAO: Nếu là Super Admin (ID nằm trong whitelist) -> Thả xích cho qua thẳng
            $superAdmins = $perms['super_admins'] ?? [];
            if (in_array((int)$userId, $superAdmins, true)) {
                return;
            }

            try {
                $capsule = App::get('db');
                $user = $capsule::table('users')->where('id', $userId)->first();

                if (!$user || $user->status !== 'active') {
                    self::destroySessionAndRedirect();
                }

                $currentFingerprint = md5($request->getClientIp() . $request->headers->get('User-Agent', ''));
                if ($currentFingerprint !== ($_SESSION['user_fingerprint'] ?? '')) {
                    self::destroySessionAndRedirect();
                }

                // 🧬 PHÂN TÍCH MẢNG QUYỀN ĐỘNG TỪ DATABASE
                $userPermissions = json_decode($user->permissions ?? '[]', true) ?? [];
                $requiredPermission = $targetRules['required_permission'] ?? '';

                // So khớp đặc quyền
                if (in_array($requiredPermission, $userPermissions, true) || in_array('manage_all', $userPermissions, true)) {
                    $hasPrivilege = true;
                }

            } catch (\Throwable $e) {
                $hasPrivilege = false;
            }
        }

        // 2. NẾU KHÔNG ĐỦ ĐẶC QUYỀN -> KÍCH NỔ LỆNH PHẠT
        if (!$hasPrivilege) {
            $errorType = $targetRules['error_type'] ?? '403';

            if ($errorType === '404' || str_starts_with($currentUri, $adminPath . '/') || $currentUri === $adminPath) {
                \App\Security\ErrorHandler::renderErrorPage(404, 'Not Found');
            }

            if ($errorType === 'json') {
                header('Content-Type: application/json; charset=utf-8');
                header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
                echo json_encode(['success' => false, 'error' => 'Invalid module privileges.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            \App\Security\ErrorHandler::renderErrorPage(403, 'Forbidden');
        }
    }

    private static function destroySessionAndRedirect(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
        RememberMeService::clearCookie();
        \App\Security\ErrorHandler::renderErrorPage(403, 'Forbidden');
    }
}
