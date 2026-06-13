<!DOCTYPE html>
<html lang="{$current_lang}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$page_title|default:"VietAdvisor Platform"}</title>

    <!-- 🚀 HẠ TẦNG BIỂU TƯỢNG ĐA NỀN TẢNG ĐỒNG BỘ COOKIE-FREE DOMAIN -->
    <link rel="icon" type="image/png" href="{$assets_url}images/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="{$assets_url}images/favicon.svg" />
    <link rel="shortcut icon" href="{$assets_url}images/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="{$assets_url}images/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="VietAdvisor" />
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

        .lang-switch {
            text-align: right;
            margin-bottom: 20px;
            font-size: 14px;
            color: #666;
        }

        .lang-switch a {
            margin-left: 12px;
            text-decoration: none;
            color: #0056b3;
            font-weight: 500;
            padding: 2px 6px;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .lang-switch a:hover {
            background: #e9ecef;
            color: #003d82;
        }

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

    <!-- 🌐 PHẦN 1: HEADER ĐẦU TRANG DÙNG CHUNG -->
    <header>
        <h1>{trans key="homepage_welcome"}</h1>
        <p>{trans key="homepage_subtitle"}</p>
    </header>

    <div class="container">
        <!-- 🌐 PHẦN 2: BỘ CHUYỂN ĐỔI NGÔN NGỮ QUY HOẠCH TRÊN LAYOUT MẸ -->
        <div class="lang-switch">
            <span>{trans key="switch_language"}:</span>
            <span class="active-lang">{$current_lang|upper}</span>

            {foreach from=$supported_languages key=lang_code item=subdomain}
                {if $lang_code !== $current_lang}
                    {if $subdomain === 'root'}
                        {$target_url = "`$app_scheme``$app_domain`/"}
                    {else}
                        {$target_url = "`$app_scheme``$subdomain`.`$app_domain`/"}
                    {/if}
                    <a href="{$target_url}">{$lang_code|upper}</a>
                {/if}
            {/foreach}
        </div>

        <!-- 📝 PHẦN 3: MAIN DYNAMIC CONTENT SLOTS -->
        {block name="content"}{/block}
    </div>

    <!-- 🌐 PHẦN 4: FOOTER CHÂN TRANG DÙNG CHUNG -->
    <footer>
        <p>&copy; {$smarty.now|date_format:"%Y"} VietAdvisor Platform. All rights reserved.</p>
    </footer>

</body>

</html>
