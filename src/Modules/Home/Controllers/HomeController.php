<?php

namespace App\Modules\Home\Controllers;

// Chốt chặn bảo mật tầng file: Cấm truy cập trực tiếp từ trình duyệt
if (!defined('EXECUTION_ALLOWED')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
    echo '<h1>403 Forbidden</h1><p>Direct access to this script is forbidden.</p>';
    exit;
}

/**
 * ==============================================================================
 * 🏠 CONTROLLER TRANG CHỦ CHUYÊN TRÁCH (HOME ACTION CONTROLLER)
 * ==============================================================================
 */
class HomeController extends ModuleHomeController
{
    /**
     * Hàm xử lý chính (Action) kích hoạt hiển thị giao diện trang chủ
     * Đmanager: Đã xóa hoàn toàn khối switch-case gán chữ cứng bẩn mã nguồn
     */
    public function index(): void
    {
        // 🔥 KÍCH NỔ TẠM THỜI: Bỏ dấu comment dòng dưới để chạy khởi tạo Database tự động, xong thì khóa lại
        // \App\Services\DatabaseBuilder::run();

        // Giả lập số lượng bài viết hiện tại lấy từ hệ thống
        $articlesCount = 26;

        // 1. Khởi tạo mảng dữ liệu mặc định dùng chung
        $viewData = [
            'page_title'        => trans('home_page_title'),
            'latest_insights'   => [],
            'featured_advisors' => [],
            'detected_country'  => \App\Security\GeoIP::getCountryCode(),
            
            // 🔥 BỔ SUNG BIẾN NÀY: Đẩy số lượng bài viết ra ngoài giao diện Smarty
            'articles_count'    => $articlesCount 
        ];

        // 2. XUẤT GIAO DIỆN SIÊU TIN GỌN XUYÊN THỦNG SMARTY
        $this->renderModule('index.tpl', $viewData);
    }
}
