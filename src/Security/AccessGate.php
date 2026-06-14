<?php

namespace App\Security;

use App\App;

if (!defined('EXECUTION_ALLOWED')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
    exit;
}

class AccessGate
{
    /**
     * Kiểm duyệt quyền truy cập toàn sàn (Military-Grade Access Gatekeeper)
     * Tích hợp cơ chế tự động tái sinh Token Ghi nhớ đăng nhập (Remember Me Token Rotation)
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

        // 3. KHỞI ĐỘNG HẠ TẦNG REMEMBER ME NẾU SESSION RAM TRỐNG RỖNG
        if (!isset($_SESSION['user_id'])) {
            self::handleAutoLoginWithRememberMe();
        }

        // 4. BIỆN PHÁP XÁC THỰC HAI CHIỀU THẮT CHẶT ĐỐI CHIẾU LIVE DB
        $userRole = 'guest';
        $userId = $_SESSION['user_id'] ?? null;

        if ($userId !== null) {
            try {
                $capsule = App::get('db');
                $user = $capsule::table('users')->where('id', $userId)->first();

                // Chốt chặn trạng thái tài khoản thời gian thực (Tránh lỗi trạng thái ảo)
                if (!$user || $user->status !== 'active') {
                    self::destroySessionAndRedirect();
                }

                // Khóa dấu vân tay thiết bị (Fingerprint) chống cướp mã PHPSESSID mạng
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

        // 5. ĐỐI CHIẾU PHÂN QUYỀN VÀ KHỞI NỔ PHẠT TRẢM
        self::verifyRole($userRole, $targetRules, $currentUri, $adminPath);
    }

    /**
     * Cơ chế tự động bốc Cookie 3 chân, đối chiếu chéo và xoay vòng mã Token vĩnh cửu
     */
    private static function handleAutoLoginWithRememberMe(): void
    {
        $request = App::request();
        // Đọc tên cookie động cấu hình bảo mật từ hệ thống
        $cookieName = 'va_remember';
        $cookieValue = $request->cookies->get($cookieName);

        if (empty($cookieValue)) {
            return;
        }

        // Tách chuỗi 3 chân: User_ID - Selector_Token - Validator_Token
        $parts = explode('-', $cookieValue);
        if (count($parts) !== 3) {
            self::clearRememberCookie($cookieName);
            return;
        }

        list($userId, $selector, $validatorRaw) = $parts;
        $currentTime = date('Y-m-d H:i:s');

        try {
            $capsule = App::get('db');

            // Tìm dòng log khớp với công cụ Selector định vị công khai
            $tokenRow = $capsule::table('user_remember_tokens')
                ->where('selector', $selector)
                ->where('user_id', (int)$userId)
                ->where('expires_at', '>', $currentTime)
                ->first();

            if (!$tokenRow) {
                self::clearRememberCookie($cookieName);
                return;
            }

            // BĂM BIẾN ĐỐI CHIẾU: Băm SHA-256 mã thô từ trình duyệt để so khớp với DB bảo mật
            if (!hash_equals($tokenRow->validator_hash, hash('sha256', $validatorRaw))) {
                // ⚠️ BÁO ĐỘNG ĐỎ: Nếu Selector đúng nhưng Validator sai -> Dấu hiệu Cookie bị hack/sửa đổi
                // Xóa toàn bộ token của User này dưới DB để cưỡng ép log out bảo vệ thiết bị chủ
                $capsule::table('user_remember_tokens')->where('user_id', (int)$userId)->delete();
                self::clearRememberCookie($cookieName);
                return;
            }

            // --- THÔNG QUAN THÀNH CÔNG: KHỞI TẠO LẠI PHIÊN LÀM VIỆC AN TOÀN ---
            $user = $capsule::table('users')->where('id', (int)$userId)->where('status', 'active')->first();
            if (!$user) {
                self::clearRememberCookie($cookieName);
                return;
            }

            // Tái thiết lập Session RAM
            $_SESSION['user_id'] = $user->id;
            $_SESSION['user_role'] = $user->role;
            $_SESSION['user_fingerprint'] = md5($request->getClientIp() . $request->headers->get('User-Agent', ''));

            // 🔥 THUẬT TOÁN XOAY VÒNG BẤT KHẢ PHÁ HỦY (TOKEN ROTATION MECHANISM)
            // Hủy bỏ bộ Selector và Validator cũ ngay lập tức
            $capsule::table('user_remember_tokens')->where('id', $tokenRow->id)->delete();

            // Sinh bộ mã 3 chân mới tinh thế chân cho lượt truy cập ngày mai
            $newSelector = bin2hex(random_bytes(8)); // 16 ký tự công khai
            $newValidatorRaw = bin2hex(random_bytes(16)); // 32 ký tự bí mật
            $newValidatorHash = hash('sha256', $newValidatorRaw);
            $expiresTimestamp = time() + (30 * 86400); // Gia hạn thêm 30 ngày

            // Nạp bộ mã mới vào MySQL
            $capsule::table('user_remember_tokens')->insert([
                'user_id' => $user->id,
                'selector' => $newSelector,
                'validator_hash' => $newValidatorHash,
                'expires_at' => date('Y-m-d H:i:s', $expiresTimestamp)
            ]);

            // Đóng đũa ghi đè bộ mã mới đè chết Cookie cũ trên trình duyệt của khách
            $newCookieValue = $user->id . '-' . $newSelector . '-' . $newValidatorRaw;

            // Gửi lệnh set cookie an toàn qua Header máy chủ
            $secureFlag = $request->isSecure() ? '; Secure' : '';
            header("Set-Cookie: {$cookieName}={$newCookieValue}; Expires=" . gmdate('D, d M Y H:i:s \G\M\T', $expiresTimestamp) . "; Path=/; HttpOnly; SameSite=Lax{$secureFlag}", false);

        } catch (\Throwable $e) {
            // Im lặng rút lui để bảo vệ vòng đời Request nếu DB xảy ra độ trễ mạng
        }
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
        self::clearRememberCookie('va_remember');
        \App\Security\ErrorHandler::renderErrorPage(403, 'Forbidden');
    }

    private static function clearRememberCookie(string $cookieName): void
    {
        header("Set-Cookie: {$cookieName}=deleted; Expires=" . gmdate('D, d M Y H:i:s \G\M\T', time() - 3600) . "; Path=/; HttpOnly; SameSite=Lax", false);
    }
}
