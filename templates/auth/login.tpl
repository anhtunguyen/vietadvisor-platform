<!-- 🚀 THỪA KẾ TOÀN DIỆN HẠ TẦNG TỪ LAYOUT MẸ -->
{extends file='layout.tpl'}

{block name="content"}
<div style="max-width: 450px; margin: 60px auto; padding: 30px; background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
    
    <h2 style="text-align: center; color: #0056b3; margin-bottom: 25px;">{trans key="login_form_btn"}</h2>

    <!-- Khối thông báo lỗi động truyền từ Session Flash -->
    {if !empty($error)}
        <div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; border-left: 4px solid #dc3545;">
            {$error}
        </div>
    {/if}

    <form action="{$smarty.const.ROOT_URL}auth/login" method="POST" style="display: flex; flex-direction: column; gap: 18px;">
        
        <div style="display: flex; flex-direction: column; gap: 6px;">
            <label style="font-weight: 500; font-size: 14px; color: #444;">Email Address</label>
            <input type="email" name="email" required placeholder="example@vietadvisor.com" style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 15px;">
        </div>

        <div style="display: flex; flex-direction: column; gap: 6px;">
            <label style="font-weight: 500; font-size: 14px; color: #444;">Password</label>
            <input type="password" name="password" required placeholder="••••••••" style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 15px;">
        </div>

        <!-- 🛡️ Ô TÍCH CHỌN REMEMBER ME KÍCH HOẠT HỎA LỰC 3 CHÂN BẢO MẬT -->
        <div style="display: flex; align-items: center; gap: 8px; margin-top: 5px;">
            <input type="checkbox" name="remember_me" value="1" id="remember_me" style="width: 16px; height: 16px; cursor: pointer;">
            <label App for="remember_me" style="font-size: 14px; color: #555; user-select: none; cursor: pointer;">
                {trans key="remember_me_label"|default:"Remember me on this device"}
            </label>
        </div>

        <button type="submit" style="background: #0056b3; color: white; border: none; padding: 12px; border-radius: 4px; font-size: 16px; font-weight: bold; cursor: pointer; margin-top: 10px; transition: background 0.2s;">
            {trans key="login_form_btn"}
        </button>

    </form>
    
    <div style="text-align: center; margin-top: 20px; font-size: 14px;">
        <a href="{$smarty.const.ROOT_URL}" style="color: #666; text-decoration: none;">&larr; {trans key="back_to_home" default="Back to Homepage"}</a>
    </div>
</div>
{/block}
