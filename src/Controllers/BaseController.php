<?php

namespace App\Controllers;

use App\App;
use Smarty;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\Loader\YamlFileLoader;

// Chốt chặn bảo mật tầng file
if (!defined('EXECUTION_ALLOWED')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
    echo '<h1>403 Forbidden</h1><p>Direct access to this script is forbidden.</p>';
    exit;
}

abstract class BaseController
{
    /**
     * Thực thể quản lý giao diện Smarty
     */
    protected Smarty $smarty;

    /**
     * Thực thể quản lý đa ngôn ngữ Symfony Translator
     */
    protected Translator $translator;

    /**
     * Mã ngôn ngữ hiện tại của Request (vi, ru, en)
     */
    protected string $currentLang;

    /**
     * Khởi tạo các cấu phần dùng chung cho toàn bộ website
     */
    public function __construct()
    {
        // 1. Nhận diện ngôn ngữ dựa trên Subdomain hiện tại để xử lý Localization
        $this->detectLanguageAndSubdomain();

        // 2. Khởi tạo và cấu hình hệ thống Đa ngôn ngữ (Symfony Translation - Chọn lọc thông minh)
        $this->initializeTranslator();

        // 3. Khóa dấu vân tay múi giờ của khách hàng từ Cookie đưa vào tầng Backend
        $this->syncClientTimezone();

        // 4. Khởi tạo và cấu hình hệ thống Giao diện (Smarty Templates)
        $this->initializeSmarty();
    }

    /**
     * Tự động nhận diện ngôn ngữ dựa theo Host / Subdomain hiện tại
     */
    private function detectLanguageAndSubdomain(): void
    {
        $host = App::request()->getHost();
        $config = require ROOT_DIR . '/config/app.php';
        $supportedLangs = $config['localization']['supported_languages'] ?? [];
        $baseDomain = $config['domain'] ?? 'localhost';

        $this->currentLang = $config['localization']['default_language'] ?? 'en';

        // Duyệt danh sách ngôn ngữ trong .env để xem Host hiện tại có khớp với Subdomain nào không
        foreach ($supportedLangs as $langCode => $subdomain) {
            if ($subdomain !== 'root' && $host === $subdomain . '.' . $baseDomain) {
                $this->currentLang = $langCode;
                break;
            }
        }

        // Đóng dấu ngôn ngữ hiện hành vào session hệ thống để đồng bộ toàn bộ app
        $_SESSION['va_lang'] = $this->currentLang;
    }

    /**
     * Cấu hình dịch thuật thông minh: Chỉ nạp ĐÚNG ngôn ngữ mặc định và ngôn ngữ Subdomain hiện hành
     * Giải pháp tối ưu hóa bộ nhớ RAM, triệt tiêu gánh nặng I/O khi hệ thống mở rộng đa quốc gia
     */
    private function initializeTranslator(): void
    {
        // Nạp file cấu hình tập trung hệ thống
        $config = require ROOT_DIR . '/config/app.php';
        $isDebug = $config['debug'] ?? true;

        // 🧬 ĐỒNG BỘ ĐỘNG CHỐT CHẶN CACHE: 
        // Nếu là Dev -> Trả về null (Tắt cache). Nếu là Prod -> Lưu vào thư mục templates_c/cache/translations
        $cacheDir = $isDebug ? null : ROOT_DIR . '/templates/templates_c/cache/translations';

        // Khởi tạo bộ dịch cốt lõi Symfony bám sát theo điều phối hạ tầng
        $this->translator = new Translator($this->currentLang, null, $cacheDir);
        $this->translator->addLoader('yaml', new YamlFileLoader());

        $defaultLang = $config['localization']['default_language'] ?? 'en';

        // Luôn nạp file ngôn ngữ mặc định làm bệ đỡ chân dự phòng Fallback
        $defaultFile = ROOT_DIR . "/translations/messages.{$defaultLang}.yaml";
        if (is_file($defaultFile)) {
            $this->translator->addResource('yaml', $defaultFile, $defaultLang);
        }

        // Chỉ nạp thêm file Subdomain nếu nó khác với ngôn ngữ mặc định
        if ($this->currentLang !== $defaultLang) {
            $currentFile = ROOT_DIR . "/translations/messages.{$this->currentLang}.yaml";
            if (is_file($currentFile)) {
                $this->translator->addResource('yaml', $currentFile, $this->currentLang);
            }
        }

        $this->translator->setFallbackLocales([$defaultLang]);

        App::bind('translator', $this->translator);
        App::bind('locale', (object)['current' => $this->currentLang]);
    }

    /**
     * Đọc Cookie múi giờ động được quy hoạch từ .env để định hình luồng xử lý của PHP
     */
    private function syncClientTimezone(): void
    {
        $request = App::request();
        $config = require ROOT_DIR . '/config/app.php';

        // Bóc chính xác tên Cookie từ phân khu quy hoạch bảo mật (.env -> config/app.php)
        $cookieTzName = $config['security']['cookies_ux']['timezone'] ?? 'va_tz';
        $clientTimezone = $request->cookies->get($cookieTzName);

        if (!empty($clientTimezone)) {
            // TẤM KHIÊN BẢO MẬT: Xác thực tính hợp pháp của chuỗi múi giờ gửi lên
            if (in_array($clientTimezone, \DateTimeZone::listIdentifiers(), true)) {
                // Ép luồng chạy hiện tại của PHP chạy theo đúng múi giờ địa phương của khách
                date_default_timezone_set($clientTimezone);
            }
        }

        // Đăng ký Múi giờ đang hoạt động vào Core Registry để sử dụng ở các phân hệ khác
        App::bind('current_timezone', (object)['name' => date_default_timezone_get()]);
    }

    /**
     * Thiết lập cấu trúc thư mục và quy trình biên dịch của Smarty
     */
    private function initializeSmarty(): void
    {
        $this->smarty = new Smarty();

        // Cấu hình các thư mục bắt buộc theo sơ đồ kiến trúc chuẩn hóa
        $this->smarty->setTemplateDir(ROOT_DIR . '/templates');
        $this->smarty->setCompileDir(ROOT_DIR . '/templates/templates_c');

        // Đăng ký hàm tùy biến trans() đồng bộ thẳng vào trong tệp tin View giao diện (.tpl)
        // Hỗ trợ cả cơ chế đếm số nhiều nâng cao ngay ngoài mặt tiền Smarty
        // Cú pháp sử dụng trong file tpl: {trans key="active_advisors_count" count=5}
        $this->smarty->registerPlugin('function', 'trans', function ($params) {
            $key = $params['key'] ?? '';
            $count = isset($params['count']) ? (int)$params['count'] : null;
            $replace = [];
            
            if ($count !== null) {
                $replace['%count%'] = $count;
            }
            
            // 🔥 ĐÃ ĐỒNG BỘ: Tuân thủ cấu pháp chuẩn id, parameters, domain, locale
            return $this->translator->trans($key, $replace, 'messages', $this->currentLang);
        });

        // ĐỒNG BỘ TÀI NGUYÊN ĐỘNG XUẤT RA VIEW SMARTY (TỐI ƯU HẠ TẦNG TUYỆT ĐỐI)
        $config = require ROOT_DIR . '/config/app.php';

        $this->smarty->assign([
            // 1. Phân hệ định danh và ngôn ngữ điều phối cục bộ
            'current_lang'        => $this->currentLang,

            // 2. Bản đồ mạng lưới URL hệ thống (Bóc tách file tĩnh Cookie-Free và liên kết định tuyến)
            'root_url'            => ROOT_URL,
            'assets_url'          => ASSETS_URL,

            // 3. Thông tin tên miền và giao thức động lấy từ file cấu hình trung tâm app.php
            'app_domain'          => $config['domain'] ?? 'vietadvisor.test',
            'app_scheme'          => \App\App::request()->isSecure() ? 'https://' : 'http://',

            // 4. Mảng danh sách ngôn ngữ động tự bóc tách từ .env (Zero-touch UI)
            'supported_languages' => $config['localization']['supported_languages'] ?? [],

            // 5. Tên Cookie Múi giờ động đã bóc tách cờ hiệu bảo mật :1 / :0
            'cookie_tz_name'      => $config['security']['cookies_ux']['timezone'] ?? 'va_tz',
            'client'              => \App\App::get('client_info')
        ]);

        // Đăng ký View Smarty vào App Core Registry
        App::bind('view', $this->smarty);
    }

    /**
     * Hàm hỗ trợ nhanh (Helper Method) để render giao diện ngắn gọn trong Controller con
     *
     * @param string $templateName Tên tệp giao diện (Ví dụ: 'home/index.tpl')
     * @param array $data Mảng dữ liệu muốn truyền ra màn hình
     */
    protected function render(string $templateName, array $data = []): void
    {
        if (!empty($data)) {
            $this->smarty->assign($data);
        }
        $this->smarty->display($templateName);
    }
}
