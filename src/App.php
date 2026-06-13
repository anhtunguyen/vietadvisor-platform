<?php

namespace App;

use Symfony\Component\HttpFoundation\Request;
use Illuminate\Database\Capsule\Manager as Capsule;
use Smarty;
use Symfony\Component\Translation\Translator;
use Bramus\Router\Router;

// Chốt chặn bảo mật tầng file
if (!defined('EXECUTION_ALLOWED')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
    echo '<h1>403 Forbidden</h1><p>Direct access to this script is forbidden.</p>';
    exit;
}

class App
{
    /**
     * Mảng lưu trữ tập trung các thực thể (Instances) hệ thống
     */
    private static array $instances = [];

    /**
     * Đăng ký một dịch vụ vào kho lưu trữ trung tâm
     *
     * @param string $key Tên định danh dịch vụ
     * @param object $instance Thực thể đối tượng tương ứng
     */
    public static function bind(string $key, object $instance): void
    {
        self::$instances[$key] = $instance;
    }

    /**
     * Lấy ra một dịch vụ từ kho lưu trữ trung tâm
     *
     * @param string $key Tên định danh dịch vụ cần gọi
     * @return object Thực thể đối tượng đã đăng ký
     * @throws \Exception Nếu dịch vụ chưa được khởi tạo
     */
    public static function get(string $key): object
    {
        if (!isset(self::$instances[$key])) {
            throw new \Exception("Kiến trúc Core lỗi: Dịch vụ '{$key}' chưa được đăng ký vào App Registry.");
        }
        return self::$instances[$key];
    }

    /**
     * Gọi nhanh thực thể Request (symfony/http-foundation)
     * Giúp đọc Header, Cookie, Body POST và IP thực của khách an toàn
     */
    public static function request(): Request
    {
        return self::get('request');
    }

    /**
     * Gọi nhanh thực thể Database Eloquent ORM (illuminate/database)
     * Phục vụ các câu lệnh Query Builder trực tiếp
     */
    public static function db(): Capsule
    {
        return self::get('db');
    }

    /**
     * Gọi nhanh bộ kích hoạt định tuyến (bramus/router)
     */
    public static function router(): Router
    {
        return self::get('router');
    }

    /**
     * Gọi nhanh thực thể hiển thị giao diện View (smarty/smarty)
     */
    public static function view(): Smarty
    {
        return self::get('view');
    }

    /**
     * Gọi nhanh thực thể đa ngôn ngữ (symfony/translation)
     */
    public static function translator(): Translator
    {
        return self::get('translator');
    }

    /**
     * Kiểm tra xem một thực thể/dịch vụ đã được đăng ký trong Registry chưa
     * @param string $key Tên định danh của dịch vụ (Ví dụ: 'translator', 'db', 'view')
     * @return bool True nếu đã tồn tại, False nếu chưa được bind
     */
    public static function has(string $key): bool {
        // Giả sử mảng tĩnh lưu trữ thực thể bên trong lớp App của bạn tên là $registry hoặc $instances
        // Bạn hãy sửa lại tên mảng tự động bám sát theo biến static mảng có sẵn trong file src/App.php nhé
        return isset(self::$instances[$key]) || array_key_exists($key, self::$instances);
    }
}
