<?php
/**
 * ==============================================================================
 * 🚀 GLOBAL HELPER FUNCTIONS - VIETADVISOR PLATFORM SYSTEM
 * ==============================================================================
 */

if (!defined('EXECUTION_ALLOWED')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
    echo '<h1>403 Forbidden</h1><p>Direct access to this script is forbidden.</p>';
    exit;
}

if (!function_exists('trans')) {
    /**
     * Hàm dịch thuật ngôn ngữ toàn cục chuẩn Framework Symfony 5/6/7 YAML (Hỗ trợ Số nhiều)
     * ĐÃ SỬA LỖI KIỂU DỮ LIỆU: Bóc tách thuộc tính ->current thô sạch cấp phát cho Symfony
     */
    function trans(string $key, array $replace = [], ?int $number = null): string {
        try {
            if (!\App\App::has('translator')) {
                return $key;
            }

            $translator = \App\App::get('translator');

            // 🧬 TRÍCH XUẤT CHUỖI THÔ SẠCH TỪ ĐỐI TƯỢNG REGISTRY (VÁ LỖI STDCLASS)
            $currentLocale = 'en'; // Cột mốc dự phòng mặc định
            if (\App\App::has('locale')) {
                $localeObj = \App\App::get('locale');
                
                // Nếu dữ liệu trả về từ Registry đúng chuẩn là một đối tượng OOP
                if (is_object($localeObj)) {
                    $currentLocale = $localeObj->current ?? 'en'; // Bóc lấy chuỗi "vi", "en", "ru"
                } else {
                    $currentLocale = (string)$localeObj; // Dự phòng nếu là chuỗi thô
                }
            }

            // Cơ chế Plural tự động kích hoạt khi có tham số %count% nằm trong mảng $replace
            if ($number !== null) {
                $replace['%count%'] = $number;
            }

            // Gọi lệnh dịch thuật tuyệt đối tuân thủ tham số của Symfony: id, parameters, domain, locale
            // Tham số thứ 4 ($currentLocale) lúc này đã là một String chuẩn 100%
            return $translator->trans($key, $replace, 'messages', $currentLocale);

        } catch (\Throwable $e) {
            // Tấm khiên bảo mật tầng sâu: Trả về chính cái Key nếu file YAML bị rách cấu trúc
            return $key;
        }
    }
}

if (!function_exists('view_data')) {
    function view_data(mixed $data, bool $exit = true): void {
        echo "<pre style='background: #1e1e1e; color: #76c73c; padding: 15px; border-radius: 5px; font-family: monospace;'>";
        echo htmlspecialchars(print_r($data, true));
        echo "</pre>";
        if ($exit) exit;
    }
}
