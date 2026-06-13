<?php

namespace App\Security;

use App\App;

// Chốt chặn bảo mật tầng tệp tin: Cấm truy cập trực tiếp file này từ trình duyệt
if (!defined('EXECUTION_ALLOWED')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
    exit;
}

class RateLimiter
{
    /**
     * Kiểm tra tần suất gửi gói tin thông minh (Method-Based Smart Rate Limiter)
     */
    public static function check(): void
    {
        $request = App::request();
        $ip = $request->getClientIp();

        // 🟢 BƯỚC 1: SÀNG LỌC THÔ (EARLY RETURN) - Bỏ qua máy phát triển cục bộ
        if (empty($ip) || $ip === '::1' || $ip === '127.0.0.1') {
            return;
        }

        // Nạp bộ cấu hình dynamic tập trung từ file cấu hình hệ thống app.php
        $configApp     = require ROOT_DIR . '/config/app.php';
        $timeWindow    = $configApp['rate_limit']['time_window'] ?? 10;
        $blockDuration = $configApp['rate_limit']['block_duration'] ?? 300;
        $staticExts    = $configApp['static_extensions'] ?? 'css|js|gif|jpeg|jpg|png|svg|webp|ico|webmanifest';

        // Miễn trừ hoàn toàn cho các tệp tài nguyên tĩnh Stateless cấu hình từ .env
        $path = trim($request->getRequestUri(), '/');
        if (preg_match('/\.(' . $staticExts . ')$/i', $path)) {
            return;
        }

        // 🧬 BƯỚC 2: PHÂN LOẠI TẢI TRỌNG REQUEST (METHOD-BASED GOVERNANCE)
        $httpMethod = strtoupper($request->getMethod());

        if ($httpMethod === 'GET') {
            // Ngưỡng thoáng cho người dùng lướt đọc giao diện thông thường
            $maxRequests = $configApp['rate_limit']['max_requests_get'] ?? 60;
        } else {
            // Siết chặt tối đa đối với POST, PUT, DELETE chống spam form, login brute-force, ddos ghi dữ liệu
            $maxRequests = $configApp['rate_limit']['max_requests_write'] ?? 5;
        }

        $currentTime = time();
        $key = 'rate_limit_' . md5($ip);

        // 🧬 ĐIỀU PHỐI HẠ TẦNG ĐA CỔNG CHẠY TRÊN RAM CACHE HOẶC FILE TẠM
        if (App::has('cache_engine')) {
            self::checkViaMemory($key, $currentTime, $ip, $maxRequests, $timeWindow, $blockDuration);
        } else {
            self::checkViaFile($key, $currentTime, $ip, $maxRequests, $timeWindow, $blockDuration);
        }
    }

    /**
     * Thuật toán kiểm rà chạy thẳng trên thanh RAM (Redis / Memcached Engine - Tốc độ ~0ms)
     */
    private static function checkViaMemory(string $key, int $currentTime, string $ip, int $maxRequests, int $timeWindow, int $blockDuration): void
    {
        $cache = App::get('cache_engine');
        $data = $cache->get($key) ?? ['requests' => [], 'blocked_until' => 0];

        if ($data['blocked_until'] > $currentTime) {
            self::rejectRequest($data['blocked_until'] - $currentTime);
        }

        $data['requests'] = array_filter($data['requests'], function ($ts) use ($currentTime, $timeWindow) {
            return $ts > ($currentTime - $timeWindow);
        });

        if (count($data['requests']) >= $maxRequests) {
            $data['blocked_until'] = $currentTime + $blockDuration;
            $cache->set($key, $data, $blockDuration);

            self::logToIpBlacklist($ip);
            self::rejectRequest($blockDuration);
        }

        $data['requests'][] = $currentTime;
        $cache->set($key, $data, $timeWindow);
    }

    /**
     * Thuật toán chạy file thô tối ưu phân tán (Có tự động sinh thư mục cache tầng sâu)
     */
    private static function checkViaFile(string $key, int $currentTime, string $ip, int $maxRequests, int $timeWindow, int $blockDuration): void
    {
        $cacheDir = ROOT_DIR . '/templates/templates_c/cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }

        $cacheFile = $cacheDir . '/' . $key . '.json';
        $data = ['requests' => [], 'blocked_until' => 0];

        if (is_file($cacheFile)) {
            $content = @file_get_contents($cacheFile);
            $data = json_decode($content, true) ?? $data;
        }

        if ($data['blocked_until'] > $currentTime) {
            self::rejectRequest($data['blocked_until'] - $currentTime);
        }

        $data['requests'] = array_filter($data['requests'], function ($ts) use ($currentTime, $timeWindow) {
            return $ts > ($currentTime - $timeWindow);
        });

        if (count($data['requests']) >= $maxRequests) {
            $data['blocked_until'] = $currentTime + $blockDuration;
            @file_put_contents($cacheFile, json_encode($data));

            self::logToIpBlacklist($ip);
            self::rejectRequest($blockDuration);
        }

        $data['requests'][] = $currentTime;
        @file_put_contents($cacheFile, json_encode($data));
    }

    /**
     * Kích nổ lệnh cấm cửa, xuất xưởng trang lỗi mạng mã 429 chuẩn RFC
     */
    private static function rejectRequest(int $retryAfter): void
    {
        header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 429 Too Many Requests');
        header("Retry-After: {$retryAfter}");
        header("Content-Type: text/html; charset=utf-8");
        echo "<h1>429 Too Many Requests</h1><p>Your IP is flooding the site. Please wait {$retryAfter} seconds.</p>";
        exit;
    }

    /**
     * Tự động đẩy IP flood vào danh sách phạt khóa có thời hạn (Temporary Ban System)
     */
    private static function logToIpBlacklist(string $ip): void
    {
        $configApp = require ROOT_DIR . '/config/app.php';
        $blockDuration = $configApp['rate_limit']['block_duration'] ?? 300;

        $cacheDir = ROOT_DIR . '/templates/templates_c/cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }

        $file = $cacheDir . '/temporary_banned_ips.json';
        $banList = [];

        if (is_file($file)) {
            $banList = json_decode(@file_get_contents($file), true) ?? [];
        }

        $banList[$ip] = time() + $blockDuration;
        @file_put_contents($file, json_encode($banList));
    }
}
