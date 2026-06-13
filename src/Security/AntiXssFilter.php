<?php

namespace App\Security;

use voku\helper\AntiXSS;

// Chốt chặn bảo mật tầng file
if (!defined('EXECUTION_ALLOWED')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
    echo '<h1>403 Forbidden</h1><p>Direct access to this script is forbidden.</p>';
    exit;
}

class AntiXssFilter
{
    /**
     * Thực thể AntiXSS duy nhất (Singleton Pattern ngầm) để tối ưu bộ nhớ
     */
    private static ?AntiXSS $antiXss = null;

    /**
     * Khởi tạo thực thể bộ lọc của thư viện voku
     */
    private static function init(): AntiXSS
    {
        if (self::$antiXss === null) {
            self::$antiXss = new AntiXSS();
        }
        return self::$antiXss;
    }

    /**
     * Bộ lọc dọn sạch một chuỗi văn bản thuần (String Filter)
     *
     * @param string $data Chuỗi thô đầu vào do người dùng gửi lên
     * @return string Chuỗi sạch đã loại bỏ các thẻ <script>, alert(), onerror...
     */
    public static function cleanString(string $data): string
    {
        if (empty($data)) {
            return $data;
        }
        return self::init()->xss_clean($data);
    }

    /**
     * Bộ lọc dọn sạch toàn bộ mảng dữ liệu (Array/Form Filter)
     * Tự động quét đệ quy sâu vào mọi cấp độ của mảng (Input Form phình to)
     *
     * @param array $data Mảng dữ liệu thô (Ví dụ: $_POST hoặc $request->request->all())
     * @return array Mảng dữ liệu đã được lọc sạch bóng mã độc XSS
     */
    public static function cleanArray(array $data): array
    {
        if (empty($data)) {
            return $data;
        }

        $cleanData = [];
        foreach ($data as $key => $value) {
            // Nếu phần tử bên trong lại là một mảng con, tiến hành đệ quy sâu tiếp
            if (is_array($value)) {
                $cleanData[$key] = self::cleanArray($value);
            } elseif (is_string($value)) {
                $cleanData[$key] = self::cleanString($value);
            } else {
                // Giữ nguyên kiểu dữ liệu nếu là Int, Float hoặc Boolean
                $cleanData[$key] = $value;
            }
        }

        return $cleanData;
    }
}
