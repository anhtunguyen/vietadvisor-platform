<?php

namespace App\Services;

use App\App;

// Chốt chặn bảo mật tầng file
if (!defined('EXECUTION_ALLOWED')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
    exit;
}

class CacheService
{
    /**
     * Khởi chạy hệ thống In-Memory Cache Engine động đa cổng (Redis / Memcached)
     * Đóng gói kín logic phân tách kết nối, triệt tiêu nguy cơ làm phình to file index.php
     */
    public static function boot(): void
    {
        $sessionHandler = $_ENV['SESSION_HANDLER'] ?? 'files';
        $sessionPath    = $_ENV['SESSION_PATH'] ?? '';

        // CỔNG 1: KẾT NỐI VÀ KHỞI TẠO BỘ ĐỆM REDIS
        if ($sessionHandler === 'redis' && class_exists('\Redis')) {
            try {
                $redis = new \Redis();
                $parsedUrl = parse_url($sessionPath);
                $host = $parsedUrl['host'] ?? '127.0.0.1';
                $port = $parsedUrl['port'] ?? 6379;

                // Timeout kết nối tối đa 1.0 giây để chống nghẽn máy chủ nếu dịch vụ gặp sự cố
                if (@$redis->connect($host, (int)$port, 1.0)) {
                    App::bind('cache_engine', $redis);
                }
            } catch (\Throwable $e) {
                // Tấm khiên bảo mật: Im lặng bỏ qua để hệ thống tự lùi về xài file JSON tạm cho RateLimiter
            }
        }
        // CỔNG 2: KẾT NỐI VÀ KHỞI TẠO BỘ ĐỆM MEMCACHED
        elseif ($sessionHandler === 'memcached' && class_exists('\Memcached')) {
            try {
                $memcached = new \Memcached();
                $parts = explode(':', $sessionPath);
                $host = $parts[0] ?? '127.0.0.1';
                $port = $parts[1] ?? 11211;

                $memcached->addServer($host, (int)$port);
                $stats = $memcached->getStats();

                if (!empty($stats) && isset($stats["{$host}:{$port}"])) {
                    App::bind('cache_engine', $memcached);
                }
            } catch (\Throwable $e) {
                // Fallback an toàn bảo vệ vòng đời Request hiện hành
            }
        }
    }
}
