<!-- 🚀 THỪA KẾ TOÀN DIỆN HẠ TẦNG TỪ LAYOUT MẸ -->
{extends file='layout.tpl'}

<!-- 🧬 ĐẮP RUỘT NỘI DUNG VÀO Ô TRỐNG CONTENT -->
{block name="content"}
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
            {trans key="login_cta_btn"}
        </a>
    </div>
</div>
{/block}
