<?php

namespace App\Security;

use App\App;
use GeoIp2\Database\Reader;

// Chốt chặn bảo mật tầng file
if (!defined('EXECUTION_ALLOWED')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
    echo '<h1>403 Forbidden</h1><p>Direct access to this script is forbidden.</p>';
    exit;
}

class GeoIP
{
    /**
     * Bộ đọc cơ sở dữ liệu nhị phân MaxMind (Singleton Pattern để tối ưu bộ nhớ)
     */
    private static ?Reader $reader = null;

    /**
     * Khởi tạo kết nối tới file dữ liệu tĩnh GeoLite2
     */
    private static function init(): ?Reader
    {
        if (self::$reader === null) {
            $dbPath = ROOT_DIR . '/config/GeoLite2-Country.mmdb';
            if (is_file($dbPath)) {
                self::$reader = new Reader($dbPath);
            }
        }
        return self::$reader;
    }

    /**
     * Bóc tách mã quốc gia 2 ký tự (Ví dụ: VN, RU, US) tối ưu hạ tầng tầng sâu
     * Luồng chạy: Cloudflare Headers -> Server GeoIP Modules -> Fallback MaxMind File
     */
    public static function getCountryCode(): string
    {
        // ==============================================================================
        // 🚀 LỚP 1: TẬN DỤNG CÁC BIẾN CÓ SẴN TRONG RAM (TỐC ĐỘ SIÊU TỐC - 0ms)
        // ==============================================================================

        // 1. Ưu tiên hàng đầu: Lấy từ Header của Cloudflare (Nếu site đứng sau Cloudflare)
        if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
            return strtoupper(trim($_SERVER['HTTP_CF_IPCOUNTRY']));
        }

        // 2. Ưu tiên 2: Lấy từ các biến Module GeoIP của Nginx / Apache cài sẵn trên Server
        if (!empty($_SERVER['GEOIP_COUNTRY_CODE'])) {
            return strtoupper(trim($_SERVER['GEOIP_COUNTRY_CODE']));
        }
        if (!empty($_SERVER['HTTP_GEOIP_COUNTRY_CODE'])) {
            return strtoupper(trim($_SERVER['HTTP_GEOIP_COUNTRY_CODE']));
        }
        if (!empty($_SERVER['COUNTRY_CODE'])) {
            return strtoupper(trim($_SERVER['COUNTRY_CODE']));
        }

        // ==============================================================================
        // 💾 LỚP 2: FALLBACK ĐỌC FILE NHỊ PHÂN GEOLITE2 (Dành cho XAMPP hoặc Server Thường)
        // ==============================================================================
        $reader = self::init();
        if (!$reader) {
            return 'US'; // Trả về mã US mặc định nếu toàn bộ hệ thống quét thất bại
        }

        try {
            $ip = App::request()->getClientIp();

            // Khóa chốt môi trường XAMPP local (127.0.0.1 hoặc ::1)
            if ($ip === '127.0.0.1' || $ip === '::1') {
                return 'VN'; // Mặc định trả về VN khi code offline
            }

            $record = $reader->country($ip);
            return strtoupper($record->country->isoCode);
        } catch (\Throwable $e) {
            return 'US';
        }
    }
}
