<?php

namespace App\Security;

use App\App;

// Chốt chặn bảo mật tầng file
if (!defined('EXECUTION_ALLOWED')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
    echo '<h1>403 Forbidden</h1><p>Direct access to this script is forbidden.</p>';
    exit;
}

class SystemCheck
{
    /**
     * Xuất báo cáo rà soát và kiểm định toàn bộ hạ tầng máy chủ
     */
    public static function environmentReport(): void
    {
        // CHỐT CHẶN BẢO MẬT: Chỉ cho phép chạy trang kiểm tra này trên môi trường phát triển (development/XAMPP)
        // Tuyệt đối không để rò rỉ thông tin hạ tầng trên môi trường Production thực tế
        if (($_ENV['APP_ENV'] ?? 'production') !== 'development') {
            \App\Security\ErrorHandler::renderErrorPage(403, 'Forbidden');
        }

        // Tạo khung HTML hiển thị báo cáo sạch sẽ, chuyên nghiệp
        echo "<!DOCTYPE html>";
        echo "<html>";
        echo "<head>";
        echo "  <meta charset='UTF-8'>";
        echo "  <title>VietAdvisor - Infrastructure Report</title>";
        echo "  <style>";
        echo "    body { font-family: 'Segoe UI', sans-serif; background: #f4f6f9; color: #333; padding: 40px; margin: 0; }";
        echo "    .report-card { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }";
        echo "    h2 { color: #0056b3; border-bottom: 2px solid #e9ecef; padding-bottom: 10px; margin-top: 0; }";
        echo "    ul { list-style: none; padding: 0; margin: 20px 0; }";
        echo "    li { padding: 12px 15px; border-bottom: 1px solid #f1f3f5; line-height: 1.6; font-size: 15px; }";
        echo "    li:last-child { border-bottom: none; }";
        echo "    .status-ok { color: #28a745; font-weight: bold; }";
        echo "    .status-warn { color: #fd7e14; font-weight: bold; }";
        echo "    .status-error { color: #dc3545; font-weight: bold; }";
        echo "  </style>";
        echo "</head>";
        echo "<body>";

        echo "<div class='report-card'>";
        echo "  <h2>📋 BÁO CÁO KIỂM ĐỊNH HẠ TẦNG VIETADVISOR SYSTEM</h2>";
        echo "  <ul>";

        // 1. KIỂM TRA GIAO THỨC HTTP/2 ĐANG KÍCH HOẠT
        $protocol = App::request()->server->get('SERVER_PROTOCOL');
        echo "  <li>";
        echo "    <b>Giao thức HTTP/2:</b> ";
        if (strpos($protocol, 'HTTP/2') !== false) {
            echo "<span class='status-ok'>✅ ĐÃ KÍCH HOẠT</span> (Hạ tầng tối ưu hóa luồng tải đa dữ liệu siêu tốc).";
        } else {
            echo "<span class='status-warn'>⚠️ CHƯA BẬT</span> (Server hiện tại đang phản hồi bằng giao thức thường: " . htmlspecialchars($protocol) . ").";
        }
        echo "  </li>";

        // 2. KIỂM TRA CƠ CHẾ REWRITE ĐƯỜNG DẪN ĐẸP (MOD_REWRITE)
        $hasModRewrite = function_exists('apache_get_modules') && in_array('mod_rewrite', apache_get_modules());
        echo "  <li>";
        echo "    <b>Cơ chế URL Rewrite (mod_rewrite):</b> ";
        if ($hasModRewrite) {
            echo "<span class='status-ok'>✅ HOẠT ĐỘNG HOÀN HẢO</span> (Tệp tin .htaccess cửa ngõ đã sẵn sàng bóc tách URL).";
        } else {
            echo "<span class='status-warn'>⚠️ KHÔNG THỂ QUÉT</span> (Không thể xác minh trực tiếp qua PHP. Hãy kiểm tra bằng cách gõ thử 1 URL không có thực để xem Router 404 hoạt động).";
        }
        echo "  </li>";

        // 3. KIỂM TRA TRẠNG THÁI SSL (HTTPS KẾT NỐI BẢO MẬT)
        echo "  <li>";
        echo "    <b>Trạng thái mã hóa SSL Connection:</b> ";
        if (App::request()->isSecure()) {
            echo "<span class='status-ok'>🔒 ĐANG CHẠY HTTPS (An Toàn)</span>.";
        } else {
            echo "<span class='status-warn'>🔓 ĐANG CHẠY HTTP (Thông Thường)</span> (Lưu ý: Môi trường XAMPP local có thể chạy HTTP, nhưng khi đẩy lên server thật bắt buộc phải cấu hình SSL).";
        }
        echo "  </li>";

        // 4. KIỂM TRA QUYỀN GHI THƯ MỤC BIÊN DỊCH VIEW (SMARTY COMPILE PATH)
        $compileDir = ROOT_DIR . '/templates/templates_c';
        echo "  <li>";
        echo "    <b>Quyền ghi Thư mục Đệm View (Smarty Compile Dir):</b> ";
        if (is_writable($compileDir)) {
            echo "<span class='status-ok'>✅ HỢP LỆ (Writable)</span> (Thư mục templates_c đã được cấp quyền ghi dữ liệu mượt mà).";
        } else {
            echo "<span class='status-error'>❌ LỖI PHÂN QUYỀN (Permission Denied)</span> (Hệ thống không thể biên dịch giao diện. Vui lòng cấp quyền ghi CHMOD 775 hoặc 777 cho thư mục này ngay).";
        }
        echo "  </li>";

        // 5. 🛡️ KIỂM TRA ĐỒNG BỘ CẤU HÌNH PHIÊN LÀM VIỆC (SESSION ACCESSIBILITY FLAGGING)
        try {
            $sessionCheck = App::get('session_status_applied');
            echo "  <li>";
            echo "    <b>Hạ tầng cấu hình cấu trúc Session tùy biến từ .env:</b> ";
            if ($sessionCheck->success) {
                echo "<span class='status-ok'>✅ ĐỒNG BỘ THÀNH CÔNG</span> (Môi trường cài đặt Session từ file cấu hình hoạt động tối ưu).";
            } else {
                echo "<span class='status-warn'>⚠️ BỊ MÁY CHỦ KHÓA CỨNG (Fallback Applied)</span><br>";
                echo "    <small style='color:#666;'>Lưu ý: Lệnh can thiệp ini_set bị Web Server chặn ngầm. Hệ thống lõi đã kích hoạt cơ chế Fallback tự động quay về dùng bộ quản lý lưu trữ mặc định của hệ thống. Tính năng đồng bộ đăng nhập xuyên suốt các Subdomain (vi, ru) có thể không hoạt động trên cấu hình server này.</small>";
            }
            echo "  </li>";
        } catch (\Throwable $e) {
            echo "  <li><b>Hạ tầng cấu hình Session:</b> <span class='status-error'>❌ CHƯA KHỞI CHẠY</span> (Dữ liệu cờ hiệu kiểm tra Session chưa được nạp vào Core Registry).</li>";
        }

        echo "  </ul>";
        echo "</div>";

        echo "</body>";
        echo "</html>";
        exit;
    }
}
