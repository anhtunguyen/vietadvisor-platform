<?php

namespace App\Modules\Home\Controllers;

use App\Controllers\BaseController;

// Chốt chặn bảo mật tầng file: Cấm truy cập trực tiếp từ trình duyệt
if (!defined('EXECUTION_ALLOWED')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
    echo '<h1>403 Forbidden</h1><p>Direct access to this script is forbidden.</p>';
    exit;
}

abstract class ModuleHomeController extends BaseController
{
    /**
     * Đường dẫn tương đối trỏ đến thư mục chứa giao diện (.tpl) của riêng Module Home
     */
    protected string $moduleTemplatePath = 'home/';

    /**
     * Hàm khởi tạo của Module Home
     */
    public function __construct()
    {
        // Kích hoạt hàm khởi tạo của BaseController để nạp Smarty và Translator trước
        parent::__construct();

        // Đăng ký thêm các thiết lập hoặc biến toàn cục bổ sung dùng chung cho riêng Module Home tại đây
        $this->bootModuleHome();
    }

    /**
     * Thiết lập các tài nguyên dùng chung nội bộ cho phân hệ Trang chủ
     */
    private function bootModuleHome(): void
    {
        // Ví dụ: Truyền tên module ra Smarty để xử lý active menu trên giao diện
        $this->smarty->assign([
            'active_module' => 'home'
        ]);
    }

    /**
     * Ghi đè hoặc bọc lại hàm render để tự động chèn đường dẫn thư mục 'home/' của Module
     * Giúp code ở các Controller con ngắn gọn hơn, không cần gõ đi gõ lại tên thư mục.
     *
     * @param string $templateName Tên tệp giao diện (Ví dụ: 'index.tpl')
     * @param array $data Mảng dữ liệu muốn truyền ra màn hình
     */
    protected function renderModule(string $templateName, array $data = []): void
    {
        // Tự động nối chuỗi thành 'home/index.tpl' trước khi đẩy sang BaseController xử lý
        $fullTemplatePath = $this->moduleTemplatePath . ltrim($templateName, '/');

        $this->render($fullTemplatePath, $data);
    }
}
