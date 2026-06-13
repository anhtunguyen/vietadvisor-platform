<!DOCTYPE html>
<html lang="{$current_lang}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$page_title}</title>

    <!-- 🚀 HẠ TẦNG BIỂU TƯỢNG ĐA NỀN TẢNG ĐỒNG BỘ COOKIE-FREE DOMAIN -->
    <!-- 1. Định dạng PNG 96x96 độ phân giải cao cho màn hình máy tính hiện đại -->
    <link rel="icon" type="image/png" href="{$assets_url}images/favicon-96x96.png" sizes="96x96" />
    
    <!-- 2. Định dạng SVG véc-tơ siêu nhẹ, tự co giãn sắc nét trên mọi loại màn hình 4K/Retina -->
    <link rel="icon" type="image/svg+xml" href="{$assets_url}images/favicon.svg" />
    
    <!-- 3. Fallback cho các trình duyệt cũ như Internet Explorer -->
    <link rel="shortcut icon" href="{$assets_url}images/favicon.ico" />
    
    <!-- 4. Biểu tượng dành riêng cho thiết bị Apple (iOS/iPhone/iPad) khi lưu ra màn hình chính -->
    <link rel="apple-touch-icon" sizes="180x180" href="{$assets_url}images/apple-touch-icon.png" />
    
    <!-- 5. Tên hiển thị của ứng dụng khi người dùng ghim website trên điện thoại -->
    <meta name="apple-mobile-web-app-title" content="VietAdvisor" />
    
    <!-- 6. Tệp cấu hình Manifest giúp website chạy mượt mà như một App di động (PWA chuẩn) -->
    <link rel="manifest" href="{$assets_url}images/site.webmanifest" />

    <!-- Gọi tài nguyên tĩnh chuẩn hóa, loại bỏ hoàn toàn lỗi trùng dấu // -->
    <link rel="stylesheet" href="{$assets_url}css/main.css">

    <script src="{$assets_url}js/common.js" defer></script>

    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background: #f8f9fa;
            color: #333;
        }

        header {
            background: #0056b3;
            color: white;
            padding: 20px;
            text-align: center;
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        /* Khung bọc bộ chuyển đổi ngôn ngữ */
        .lang-switch {
            text-align: right;
            margin-bottom: 20px;
            font-size: 14px;
            color: #666;
        }

        /* Định dạng cho các ngôn ngữ có thể bấm chuyển hướng */
        .lang-switch a {
            margin-left: 12px;
            text-decoration: none;
            color: #0056b3;
            font-weight: 500;
            padding: 2px 6px;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        /* Hiệu ứng rê chuột vào các ngôn ngữ khác */
        .lang-switch a:hover {
            background: #e9ecef;
            color: #003d82;
        }

        /* ĐỊNH NGHĨA CHÍNH THỨC CHO NGÔN NGỮ ĐANG SỬ DỤNG */
        .lang-switch .active-lang {
            margin-left: 12px;
            color: #ffffff;
            background: #0056b3;
            font-weight: bold;
            padding: 2px 8px;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0, 86, 179, 0.2);
            display: inline-block;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        footer {
            background: #343a40;
            color: white;
            text-align: center;
            padding: 15px;
            position: fixed;
            bottom: 0;
            width: 100%;
        }
    </style>
</head>

<body data-cookie-tz-name="{$cookie_tz_name}" data-app-domain="{$app_domain}">

    <header>
        <!-- Tự động dịch tiêu đề dựa trên file JSON ngôn ngữ của Subdomain -->
        <h1>{trans key="homepage_welcome"}</h1>
        <p>{trans key="homepage_subtitle"}</p>
    </header>

    <div class="container">
        <!-- Bộ chuyển đổi ngôn ngữ tĩnh linh hoạt bằng hằng số ROOT_URL mẹ -->
        <div class="lang-switch">
            <span>{trans key="switch_language"}:</span>

            <!-- Ngôn ngữ hiện tại luôn đứng đầu danh sách -->
            <span class="active-lang">{$current_lang|upper}</span>

            <!-- Duyệt vòng lặp để in các ngôn ngữ còn lại ra phía sau động -->
            {foreach from=$supported_languages key=lang_code item=subdomain}
                {if $lang_code !== $current_lang}
                    <!-- 🔥 SỬ DỤNG BIẾN APP_SCHEME ĐỘNG ĐỂ GIỮ NGUYÊN GIAO THỨC HTTP/HTTPS -->
                    {if $subdomain === 'root'}
                        {$target_url = "`$app_scheme``$app_domain`/"}
                    {else}
                        {$target_url = "`$app_scheme``$subdomain`.`$app_domain`/"}
                    {/if}

                    <a href="{$target_url}">{$lang_code|upper}</a>
                {/if}
            {/foreach}
        </div>

        <div class="grid">
            <!-- Phân khu 1: Giới thiệu chung -->
            <div class="card">
                <h2>{trans key="about_us_title"}</h2>
                <p>{trans key="about_us_desc"}</p>
                <a href="{$smarty.const.ROOT_URL}insights" style="color: #0056b3;">{trans key="read_more"} &rarr;</a>
            </div>

            <!-- Phân khu 2: Khai thác dữ liệu Insights truyền từ Controller con -->
            <div class="card">
                <h2>{trans key="latest_news"}</h2>
                {if empty($latest_insights)}
                    <p>{trans key="articles_found" count=$articles_count}</p>
                {else}
                    <ul>
                        {foreach from=$latest_insights item=article}
                            <li><a href="{$smarty.const.ROOT_URL}insights/post/{$article->id}">{$article->title}</a></li>
                        {/foreach}
                    </ul>
                {/if}
            </div>

            <!-- Phân khu 3: Phân hệ tài khoản và Gateway liên kết -->
            <div class="card">
                <h2>{trans key="account_zone"}</h2>
                <p>{trans key="account_zone_desc"}</p>
                <a href="{$smarty.const.ROOT_URL}auth/login" style="display:inline-block; background:#0056b3; color:white; padding:10px 20px; border-radius:4px; text-decoration:none;">
                    {trans key="login_btn"}
                </a>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; {$smarty.now|date_format:"%Y"} VietAdvisor Platform. All rights reserved.</p>
    </footer>
</body>

</html>
