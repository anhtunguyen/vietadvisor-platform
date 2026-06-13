<?php

namespace App\Security;

use App\App;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Chốt chặn bảo mật tầng file
if (!defined('EXECUTION_ALLOWED')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
    echo '<h1>403 Forbidden</h1><p>Direct access to this script is forbidden.</p>';
    exit;
}

class ErrorHandler
{
    /**
     * Thực thể quản lý ghi Log của Monolog
     */
    private static ?Logger $logger = null;

    /**
     * Kích hoạt hệ thống bắt lỗi tập trung (Global Registration)
     */
    public static function register(): void
    {
        // 1. Khởi tạo Monolog để ghi nhật ký lỗi ngầm
        self::initializeLogger();

        // 2. Đăng ký hàm bắt các ngoại lệ chưa được xử lý (Uncaught Exceptions)
        set_exception_handler([self::class, 'handleException']);

        // 3. Đăng ký hàm bắt các lỗi PHP thông thường (PHP Errors)
        set_error_handler([self::class, 'handleError']);

        // 4. Đăng ký hàm bắt lỗi chí mạng làm sập hệ thống (Fatal Errors / Shutdown)
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    /**
     * Cấu hình thư mục lưu file nhật ký lỗi bảo mật
     */
    private static function initializeLogger(): void
    {
        if (self::$logger === null) {
            self::$logger = new Logger('vietadvisor_core');

            // Tạo file log lưu tại thư mục templates_c (nơi có quyền ghi write)
            $logPath = ROOT_DIR . '/templates/templates_c/logs/app.log';

            // Thiết lập Monolog ghi log theo dòng chảy vào file
            self::$logger->pushHandler(new StreamHandler($logPath, Logger::ERROR));
        }
    }

    /**
     * Xử lý các Ngoại lệ (Exceptions) trên toàn hệ thống
     */
    public static function handleException(\Throwable $exception): void
    {
        $isDebug = ($_ENV['APP_ENV'] ?? 'production') === 'development';

        // 1. Ghi nhận lỗi chi tiết vào file Log bí mật
        if (self::$logger) {
            self::$logger->error($exception->getMessage(), [
                'file'  => $exception->getFile(),
                'line'  => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ]);
        }

        // 2. Phân phối phản hồi hiển thị ra màn hình cho khách hàng hoặc nhà phát triển
        if ($isDebug) {
            // Môi trường Lập trình: In lỗi thô chi tiết kèm Stack Trace để debug nhanh
            self::renderDebugScreen($exception);
        } else {
            // Môi trường Production thực tế: Giấu kín lỗi, hiển thị giao diện 500 lỗi hệ thống lịch sự
            self::renderErrorPage(500, 'Internal Server Error');
        }
    }

    /**
     * Chuyển đổi các lỗi PHP thông thường (Warning, Notice) thành Ngoại lệ để xử lý tập trung
     */
    public static function handleError(int $level, string $message, string $file, int $line): bool
    {
        // Nếu lỗi bị ẩn đi bởi toán tử @ (Error Suppression), bỏ qua không xử lý
        if (!(error_reporting() & $level)) {
            return false;
        }

        // Ép lỗi PHP thông thường tạo thành một thực thể ErrorException để ném vào handleException
        throw new \ErrorException($message, 0, $level, $file, $line);
    }

    /**
     * Đón đánh và bẫy các lỗi chí mạng (Fatal Errors) ngay trước khi PHP đóng luồng chạy
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();

        // Nếu phát hiện có lỗi chí mạng xảy ra làm sập trang (E_ERROR, E_PARSE, E_CORE_ERROR)
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            // Xóa sạch các nội dung HTML bị lỗi loang lổ đã in ra trước đó
            if (ob_get_length()) {
                ob_clean();
            }

            self::handleException(new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            ));
        }
    }

    /**
     * Xuất trang giao diện thông báo lỗi cấu trúc chuẩn (403, 404 hoặc 500) qua Smarty
     */
    public static function renderErrorPage(int $statusCode, string $statusText): void
    {
        if (!headers_sent()) {
            header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . " {$statusCode} {$statusText}");
        }

        try {
            // 1. 🛡️ TỰ ĐỘNG KHỞI TẠO TRANSLATOR DỰ PHÒNG NẾU CHƯA CÓ TRONG REGISTRY (Bảo vệ 403/404)
            try {
                $translator = App::translator();
            } catch (\Throwable $e) {
                // Tự bóc tách subdomain để nhận diện ngôn ngữ động
                $host = App::request()->getHost();
                $config = require ROOT_DIR . '/config/app.php';
                $supportedLangs = $config['localization']['supported_languages'] ?? [];
                $baseDomain = $config['domain'] ?? 'localhost';
                $currentLang = $config['localization']['default_language'] ?? 'en';

                foreach ($supportedLangs as $langCode => $subdomain) {
                    if ($subdomain !== 'root' && $host === $subdomain . '.' . $baseDomain) {
                        $currentLang = $langCode;
                        break;
                    }
                }

                // 1. 🛡️ TỰ ĐỘNG KHỞI TẠO TRANSLATOR DỰ PHÒNG NẾU CHƯA CÓ TRONG REGISTRY (Bảo vệ 403/404)
                try {
                    $translator = App::translator();
                } catch (\Throwable $e) {
                    $host = App::request()->getHost();
                    $config = require ROOT_DIR . '/config/app.php';
                    $supportedLangs = $config['localization']['supported_languages'] ?? [];
                    $baseDomain = $config['domain'] ?? 'localhost';
                    $defaultLang = $config['localization']['default_language'] ?? 'en';
                    $currentLang = $defaultLang;

                    foreach ($supportedLangs as $langCode => $subdomain) {
                        if ($subdomain !== 'root' && $host === $subdomain . '.' . $baseDomain) {
                            $currentLang = $langCode;
                            break;
                        }
                    }

                    // 🧬 TỰ ĐỘNG NHẬN DIỆN MÔI TRƯỜNG CHO TRANG LỖI MÁY CHỦ
                    $isDebug = $config['debug'] ?? true;
                    $cacheDir = $isDebug ? null : ROOT_DIR . '/templates/templates_c/cache/translations_err';

                    $translator = new \Symfony\Component\Translation\Translator($currentLang, null, $cacheDir);
                    $translator->addLoader('yaml', new \Symfony\Component\Translation\Loader\YamlFileLoader());

                    $defaultFile = ROOT_DIR . "/translations/messages.{$defaultLang}.yaml";
                    if (is_file($defaultFile)) {
                        $translator->addResource('yaml', $defaultFile, $defaultLang);
                    }

                    if ($currentLang !== $defaultLang) {
                        $currentFile = ROOT_DIR . "/translations/messages.{$currentLang}.yaml";
                        if (is_file($currentFile)) {
                            $translator->addResource('yaml', $currentFile, $currentLang);
                        }
                    }

                    $translator->setFallbackLocales([$defaultLang]);
                    App::bind('translator', $translator);
                    App::bind('locale', (object)['current' => $currentLang]);
                }
            }

            // 2. 🛡️ TỰ ĐỘNG KHỞI TẠO SMARTY DỰ PHÒNG NẾU CHƯA CÓ TRONG REGISTRY
            try {
                $smarty = App::view();
            } catch (\Throwable $e) {
                $smarty = new \Smarty();
                $smarty->setTemplateDir(ROOT_DIR . '/templates');
                $smarty->setCompileDir(ROOT_DIR . '/templates/templates_c');

                // Đăng ký lại hàm dịch {trans} cho Smarty hỗ trợ tham số đếm số nhiều nâng cao
                $smarty->registerPlugin('function', 'trans', function ($params) use ($translator) {
                    $key = $params['key'] ?? '';
                    $count = isset($params['count']) ? (int)$params['count'] : null;
                    $replace = [];

                    if ($count !== null) {
                        $replace['%count%'] = $count;
                    }

                    return $translator->trans($key, $replace, null);
                });

                App::bind('view', $smarty);
            }

            // 3. ĐỒNG BỘ BIẾN MÔI TRƯỜNG RA VIEW LỖI
            $config = require ROOT_DIR . '/config/app.php';
            $smarty->assign([
                'status_code'         => $statusCode,
                'status_text'         => $statusText,
                'page_title'          => "Error {$statusCode} - {$statusText}",
                'current_lang'        => $translator->getLocale(),
                'app_domain'          => $config['domain'] ?? 'vietadvisor.test',
                'supported_languages' => $config['localization']['supported_languages'] ?? []
            ]);

            // Tìm file tpl lỗi tương ứng (errors/403.tpl, errors/404.tpl...)
            $template = "errors/{$statusCode}.tpl";
            if (!$smarty->templateExists($template)) {
                $template = "errors/500.tpl";
            }

            $smarty->display($template);
        } catch (\Throwable $e) {
            // Trường hợp hệ thống sập quá nặng (Sập cả thư viện Smarty), dùng HTML thuần dự phòng bảo mật
            echo "<div style='font-family:sans-serif; text-align:center; padding:50px;'>";
            echo "  <h1 style='color:#dc3545;'>VietAdvisor - Access Error {$statusCode}</h1>";
            echo "  <p>Our platform is undergoing temporary infrastructure optimization. Please try again shortly.</p>";
            echo "</div>";
        }
        exit;
    }

    /**
     * Giao diện in lỗi chi tiết đặc quyền dành riêng cho lập trình viên (Debug Screen)
     */
    private static function renderDebugScreen(\Throwable $exception): void
    {
        if (!headers_sent()) {
            header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 500 Internal Server Error');
        }
        echo "<div style='background:#f8d7da; color:#721c24; padding:25px; border:1px solid #f5c6cb; font-family:monospace; border-radius:6px; margin:20px;'>";
        echo "  <h2 style='margin-top:0;'>🛑 [VietAdvisor Debug] Uncaught Exception</h2>";
        echo "  <p><b>Message:</b> " . htmlspecialchars($exception->getMessage()) . "</p>";
        echo "  <p><b>File:</b> " . htmlspecialchars($exception->getFile()) . " (Line: " . $exception->getLine() . ")</p>";
        echo "  <h3>🔍 Stack Trace:</h3>";
        echo "  <pre style='background:#ffffff; padding:15px; border-radius:4px; overflow:auto; max-height:400px;'>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
        echo "</div>";
        exit;
    }
}
