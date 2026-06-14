<?php

namespace App\Services;

use App\App;

if (!defined('EXECUTION_ALLOWED')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 403 Forbidden');
    exit;
}

class DatabaseBuilder
{
    /**
     * Kích nổ tự động tạo bảng và nạp dữ liệu ảo (Run Migrations and Seeders)
     * ĐÃ VÁ LỖI COMPATIBILITY: Tương thích hoàn hảo với thực thể Eloquent ORM Capsule
     */
    public static function run(): void
    {
        try {
            // 1. Bóc tách Eloquent Capsule từ Registry
            $capsule = App::get('db');

            // 2. 🔥 CHỐT CHẶN CHÍ MẠNG: Ép luồng lấy kết nối PDO thô để thực thi tệp SQL đa lệnh
            $pdo = $capsule->getConnection()->getPdo();

            echo "<div style='font-family:monospace; background:#111; color:#0f0; padding:20px; border-radius:5px; line-height:1.6;'>";
            echo "<h2>🛠️ VIETADVISOR DATABASE AUTOMATION ENGINE</h2>";

            // 3. Thực thi file Migration dựng cấu trúc bảng
            $migrationFile = ROOT_DIR . '/database/migrations.sql';
            if (is_file($migrationFile)) {
                $sql = file_get_contents($migrationFile);

                // Thực thi qua biến $pdo thô vừa trích xuất
                $pdo->exec($sql);
                echo "<p style='color:#76c73c;'>[SUCCESS] Migrations executed successfully. All tables created.</p>";
            } else {
                echo "<p style='color:#dc3545;'>[ERROR] Migration file not found.</p>";
            }

            // 4. Thực thi file Seeder nạp dữ liệu mồi
            $seederFile = ROOT_DIR . '/database/seeders.sql';
            if (is_file($seederFile)) {
                $sql = file_get_contents($seederFile);

                // Thực thi qua biến $pdo thô vừa trích xuất
                $pdo->exec($sql);
                echo "<p style='color:#76c73c;'>[SUCCESS] Seeders executed successfully. Dynamic multilingual mock data loaded.</p>";
            } else {
                echo "<p style='color:#dc3545;'>[ERROR] Seeder file not found.</p>";
            }

            echo "<hr><p style='color:#00bcff;'>🎉 [DONE] Database sync completed. Please remove this trigger before moving to production.</p>";
            echo "</div>";
            exit;
        } catch (\Throwable $e) {
            echo "<div style='font-family:monospace; background:#111; color:#dc3545; padding:20px; border-radius:5px;'>";
            echo "<h2>❌ DATABASE CRASH DETECTED</h2>";
            echo "<p><b>Message:</b> " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "</div>";
            exit;
        }
    }
}
