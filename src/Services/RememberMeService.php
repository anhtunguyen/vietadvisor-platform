<?php

namespace App\Services;

use App\App;

if (!defined('EXECUTION_ALLOWED')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
    exit;
}

class RememberMeService
{
    /**
     * Lấy tên Cookie được quy hoạch động từ cấu hình hệ thống
     */
    private static function getCookieName(): string
    {
        $configApp = require ROOT_DIR . '/config/app.php';
        return $configApp['security']['remember_name'] ?? 'va_rem';
    }

    /**
     * 1. HÀM TỰ ĐỘNG ĐĂNG NHẬP QUA COOKIE (Dùng cho AccessGate ở cửa ngõ)
     */
    public static function loginWithCookie(): bool
    {
        $request = App::request();
        $cookieName = self::getCookieName();
        $cookieValue = $request->cookies->get($cookieName);

        if (empty($cookieValue)) {
            return false;
        }

        $parts = explode('-', $cookieValue);
        if (count($parts) !== 3) {
            self::clearCookie();
            return false;
        }

        list($userId, $selector, $validatorRaw) = $parts;
        $currentTime = date('Y-m-d H:i:s');

        try {
            $capsule = App::get('db');

            // Tìm dòng log khớp với công cụ Selector công khai
            $tokenRow = $capsule::table('user_remember_tokens')
                ->where('selector', $selector)
                ->where('user_id', (int)$userId)
                ->where('expires_at', '>', $currentTime)
                ->first();

            if (!$tokenRow) {
                self::clearCookie();
                return false;
            }

            // Đối chiếu mã bảo mật validator đã băm SHA-256
            if (!hash_equals($tokenRow->validator_hash, hash('sha256', $validatorRaw))) {
                // Biện pháp trừng phạt: Nghi ngờ cookie lậu, xóa sạch mọi token của user này để bảo vệ tài khoản
                $capsule::table('user_remember_tokens')->where('user_id', (int)$userId)->delete();
                self::clearCookie();
                return false;
            }

            // Dựng lại Session RAM cho khách lướt web
            $user = $capsule::table('users')->where('id', (int)$userId)->where('status', 'active')->first();
            if (!$user) {
                self::clearCookie();
                return false;
            }

            $_SESSION['user_id'] = $user->id;
            $_SESSION['user_role'] = $user->role;
            $_SESSION['user_fingerprint'] = md5($request->getClientIp() . $request->headers->get('User-Agent', ''));

            // 🔥 KÍCH HOẠT THUẬT TOÁN XOAY VÒNG: Hủy mã cũ, sinh ngay mã mới
            $capsule::table('user_remember_tokens')->where('id', $tokenRow->id)->delete();
            self::createToken($user->id);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 2. HÀM TẠO MỚI TOKEN 3 CHÂN VÀ THẢ COOKIE (Dùng cho Auth\LoginController)
     */
    public static function createToken(int $userId): void
    {
        try {
            $capsule = App::get('db');
            $request = App::request();

            // Sinh bộ mã token ngẫu nhiên bảo mật cao
            $selector = bin2hex(random_bytes(8));
            $newValidatorRaw = bin2hex(random_bytes(16));
            $newValidatorHash = hash('sha256', $newValidatorRaw);
            $expiresTimestamp = time() + (30 * 86400); // Có hiệu lực trong 30 ngày

            // Nạp bộ mã vào Database
            $capsule::table('user_remember_tokens')->insert([
                'user_id'        => $userId,
                'selector'       => $selector,
                'validator_hash' => $newValidatorHash,
                'expires_at'     => date('Y-m-d H:i:s', $expiresTimestamp)
            ]);

            // Thả Cookie đóng dấu xuống trình duyệt người dùng
            $cookieName = self::getCookieName();
            $cookieValue = $userId . '-' . $selector . '-' . $newValidatorRaw;
            $secureFlag = $request->isSecure() ? '; Secure' : '';

            header("Set-Cookie: {$cookieName}={$cookieValue}; Expires=" . gmdate('D, d M Y H:i:s \G\M\T', $expiresTimestamp) . "; Path=/; HttpOnly; SameSite=Lax{$secureFlag}", false);
        } catch (\Throwable $e) {
            // Bảo vệ vòng đời runtime
        }
    }

    /**
     * 3. HÀM HỦY TOKEN DƯỚI DB VÀ XÓA COOKIE TRÊN TRÌNH DUYỆT (Dùng cho Auth\LogoutController)
     */
    public static function deleteTokenAndClearCookie(int $userId): void
    {
        try {
            $capsule = App::get('db');

            // Xóa sạch dữ liệu lưu vết token của user này dưới DB
            $capsule::table('user_remember_tokens')->where('user_id', $userId)->delete();
        } catch (\Throwable $e) {
        }

        // Clear cookie mặt tiền
        self::clearCookie();
    }

    /**
     * Hàm bổ trợ xóa sạch Cookie Remember Me ngoài mặt tiền trình duyệt
     */
    public static function clearCookie(): void
    {
        $cookieName = self::getCookieName();
        header("Set-Cookie: {$cookieName}=deleted; Expires=" . gmdate('D, d M Y H:i:s \G\M\T', time() - 3600) . "; Path=/; HttpOnly; SameSite=Lax", false);
    }
}
