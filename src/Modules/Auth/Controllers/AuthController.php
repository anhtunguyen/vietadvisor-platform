<?php

namespace App\Modules\Auth\Controllers;

use App\App;
use App\Controllers\BaseController;
use App\Services\RememberMeService;

if (!defined('EXECUTION_ALLOWED')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
    exit;
}

class AuthController extends BaseController
{
    /**
     * 🎨 Hiển thị giao diện Form Đăng nhập đa ngôn ngữ
     */
    public function showLoginForm(): void
    {
        // Nếu người dùng đã đăng nhập từ trước, tự động bẻ lái về trang chủ, tránh login lặp
        if (isset($_SESSION['user_id'])) {
            header('Location: ' . ROOT_URL);
            exit;
        }

        $viewData = [
            'page_title' => trans('login_form_btn') . ' | VietAdvisor',
            'error'      => $_SESSION['auth_error'] ?? null
        ];

        // Dọn sạch flash error sau khi đã nạp vào giao diện
        unset($_SESSION['auth_error']);

        $this->render('auth/login.tpl', $viewData);
    }

    /**
     * 🛡️ Tiếp nhận xử lý form Đăng nhập (POST Method)
     */
    public function handleLogin(): void
    {
        $request = App::request();
        $email = trim($request->request->get('email', ''));
        $password = $request->request->get('password', '');
        $rememberMe = $request->request->get('remember_me', '0');

        if (empty($email) || empty($password)) {
            $_SESSION['auth_error'] = trans('error_empty_fields' ?? 'Please fill in all fields.');
            header('Location: ' . ROOT_URL . 'auth/login');
            exit;
        }

        try {
            $capsule = App::get('db');

            // Tìm kiếm tài khoản Live trong MySQL
            $user = $capsule::table('users')->where('email', $email)->first();

            // Thực hiện đối chiếu mã hóa mật khẩu bảo mật Bcrypt mã nguồn gốc
            // Đồng thời check trạng thái tài khoản phải là 'active'
            if ($user && $user->status === 'active' && password_verify($password, $user->password)) {

                // 🧬 THÔNG QUAN THÀNH CÔNG: KHỞI TẠO ĐỘNG PHIÊN LÀM VIỆC AN TOÀN
                $_SESSION['user_id'] = $user->id;
                $_SESSION['user_role'] = $user->role;

                // Khóa chặt vân tay thiết bị (Fingerprint) bảo vệ PHPSESSID ngay lập tức
                $_SESSION['user_fingerprint'] = md5($request->getClientIp() . $request->headers->get('User-Agent', ''));

                // ⚡ CHỐT CHẶN CHÂN THỨ 3: Nếu khách bấm tích chọn Ghi nhớ đăng nhập
                if ($rememberMe === '1') {
                    RememberMeService::createToken($user->id);
                }

                // Tự động phân luồng bẻ lái URL sau khi đăng nhập thành công
                if ($user->role === 'admin') {
                    $configApp = require ROOT_DIR . '/config/app.php';
                    $adminPath = $configApp['admin_path'] ?? 'admin';
                    header('Location: ' . ROOT_URL . $adminPath);
                } else {
                    header('Location: ' . ROOT_URL);
                }
                exit;
            }

            // Gặp lỗi sai mật khẩu hoặc tài khoản bị ban ngầm
            $_SESSION['auth_error'] = trans('error_invalid_credentials' ?? 'Invalid email or password.');
            header('Location: ' . ROOT_URL . 'auth/login');
            exit;

        } catch (\Throwable $e) {
            $_SESSION['auth_error'] = 'Server configuration connection error.';
            header('Location: ' . ROOT_URL . 'auth/login');
            exit;
        }
    }

    /**
     * 🧼 Hủy diệt phiên làm việc, xóa dấu vết bóc Cookie (Logout Action)
     */
    public function handleLogout(): void
    {
        $userId = $_SESSION['user_id'] ?? null;

        if ($userId) {
            // Ủy thác lớp dịch vụ dùng chung hủy token dưới DB và bóc sạch Cookie trên máy khách
            RememberMeService::deleteTokenAndClearCookie((int)$userId);
        }

        // Hủy diệt toàn bộ mảng Session RAM hệ thống
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }

        header('Location: ' . ROOT_URL . 'auth/login');
        exit;
    }
}
