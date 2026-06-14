-- ==============================================================================
-- 🚀 VIETADVISOR PLATFORM - DATABASE SEEDERS (MOCK DATA)
-- ==============================================================================

-- Đường dẫn file: database/seeders.sql

-- 1. Nạp tài khoản người dùng mẫu (Đã nạp chuỗi băm chính chủ XAMPP máy bạn)
INSERT INTO `users` (`id`, `email`, `password`, `role`, `permissions`, `status`) VALUES
(1, 'superadmin@vietadvisor.com', '$2y$10$ROUxt5m1Aj88fjFojNmAi.bglKrtykGolcP7F5Xj5FS4EE3lgL5J6', 'admin', '["access_admin_dashboard", "manage_all"]', 'active'),
(2, 'ivan.lawyer@vietadvisor.com', '$2y$10$ROUxt5m1Aj88fjFojNmAi.bglKrtykGolcP7F5Xj5FS4EE3lgL5J6', 'advisor', NULL, 'active'),
(3, 'insight.admin@vietadvisor.com', '$2y$10$ROUxt5m1Aj88fjFojNmAi.bglKrtykGolcP7F5Xj5FS4EE3lgL5J6', 'admin', '["access_admin_dashboard", "manage_insights"]', 'active');

-- 2. Nạp hồ sơ kỹ thuật Chuyên gia
INSERT INTO `advisors` (`id`, `user_id`, `avatar`, `phone`, `rating`, `is_verified`, `speak_russian`, `speak_english`, `speak_vietnamese`) VALUES
(1, 2, 'avatar_ivan.png', '+84901234567', 4.95, 1, 1, 1, 0);

-- 3. Nạp dịch thuật 3 thứ tiếng cho Chuyên gia Ivan (Tránh lỗi vỡ trận UX bản xứ)
INSERT INTO `advisor_translations` (`advisor_id`, `locale`, `full_name`, `category`, `biography`) VALUES
('1', 'en', 'Ivan Petrov', 'Legal & Visa Expert', 'Over 10 years of experience assisting expats with business registration and long-term visas in Vietnam.'),
('1', 'vi', 'Ivan Petrov', 'Chuyên Gia Pháp Lý & Visa', 'Hơn 10 năm kinh nghiệm hỗ trợ người nước ngoài đăng ký doanh nghiệp và cấp thị thực dài hạn tại Việt Nam.'),
('1', 'ru', 'Иван Петров', 'Юридические Услуги и Визы', 'Более 10 лет опыта помощи экспатам в регистрации бизнеса и оформлении долгосрочных виз во Вьетнаме.');

-- 4. Nạp bài viết tin tức gốc mẫu
INSERT INTO `articles` (`id`, `thumbnail`, `author_id`, `status`, `views`) VALUES
(1, 'visa_guide_2026.jpg', 1, 'published', 142);

-- 5. Nạp ruột dịch thuật 3 ngôn ngữ cho bài viết (Đồng bộ chuẩn Module Insights)
INSERT INTO `article_translations` (`article_id`, `locale`, `title`, `slug`, `summary`, `content`) VALUES
(1, 'en', 'Vietnam Work Permit Guide 2026', 'vietnam-work-permit-guide-2026', 'Essential steps for expats to secure a work permit.', 'Detailed content about visa regulations and company sponsorships...'),
(1, 'vi', 'Hướng dẫn cấp Giấy phép lao động Việt Nam 2026', 'huong-dan-giay-phep-lao-dong-2026', 'Các bước cốt lõi để người nước ngoài có Work Permit.', 'Nội dung chi tiết quy trình nộp hồ sơ tại Cục Việc Làm...'),
(1, 'ru', 'Руководство по разрешению на работу во Вьетнаме 2026', 'razreshenie-na-rabotu-vietnam-2026', 'Основные шаги для получения разрешения на работу.', 'Подробная информация о правилах оформления виз и спонсорстве компаний...');

-- 6. Nạp dữ liệu cấu hình động đa ngôn ngữ toàn sàn
INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('site_name', '{"en": "VietAdvisor - Expat Directory", "vi": "VietAdvisor - Danh bạ Chuyên gia", "ru": "VietAdvisor - Справочник Экспатов"}'),
('site_description', '{"en": "The ultimate platform connecting expats with certified legal, visa, and relocation specialists in Vietnam.", "vi": "Nền tảng tối tân kết nối người nước ngoài với các chuyên gia cấp cao về pháp lý, thị thực và định cư tại Việt Nam.", "ru": "Превосходная платформа, объединяющая экспатов с сертифицированными специалистами по вопросам права, виз и иммиграции во Вьетнаме."}'),
('site_hotline', '{"en": "+84 90 123 4567", "vi": "+84 90 123 4567", "ru": "+84 90 123 4567"}'),
('site_contact_email', '{"en": "support@vietadvisor.net", "vi": "support@vietadvisor.net", "ru": "support@vietadvisor.net"}');
