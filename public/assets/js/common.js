/**
 * ==============================================================================
 * 🚀 GLOBAL CORE JAVASCRIPT PLATFORM - VIETADVISOR.NET
 * ==============================================================================
 * Quản lý đồng bộ hạ tầng Client-side, tối ưu hóa trải nghiệm và bảo mật.
 */
document.addEventListener("DOMContentLoaded", function () {

    // 1. Đồng bộ cấu hình Múi giờ Động (Dynamic Timezone Synchronization)
    (function syncClientTimezone() {
        // Đọc tên Cookie động và tên miền gốc được máy chủ nhúng sẵn trong thẻ thuộc tính của thẻ body
        const cookieName = document.body.getAttribute('data-cookie-tz-name') || 'va_tz';
        const baseDomain = document.body.getAttribute('data-app-domain') || 'vietadvisor.test';

        // Nhận diện chuỗi múi giờ vật lý thực tế của thiết bị người dùng
        const clientTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

        // Hàm bổ trợ đọc nhanh giá trị Cookie sạch (Đã tự động giải mã decode)
        const getCookieValue = (name) => {
            const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
            return match ? decodeURIComponent(match[2]) : null;
        };

        const savedTimezone = getCookieValue(cookieName);

        // Nếu chưa có cookie hoặc khách vừa di chuyển máy bay thay đổi múi giờ thiết bị
        if (!savedTimezone || savedTimezone !== clientTimezone) {
            // Tiến hành ghi Cookie động thời hạn 7 ngày, phủ sóng xuyên suốt các subdomains
            const maxAge = 7 * 24 * 60 * 60;
            document.cookie = `${cookieName}=${encodeURIComponent(clientTimezone)}; max-age=${maxAge}; path=/; domain=.${baseDomain}; SameSite=Lax`;

            // 🛡️ CHỐT CHẶN PHÁ VÒNG LẶP (ANTI-RELOAD LOOP)
            const sessionReloadKey = `${cookieName}_reloaded`;
            if (!sessionStorage.getItem(sessionReloadKey)) {
                sessionStorage.setItem(sessionReloadKey, '1');
                window.location.reload();
            }
        }
    })();

    // 2. Chuyển đổi định dạng Thời gian sang giờ địa phương của khách (UX Local Time Converter)
    (function convertToLocalTime() {
        const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
        document.querySelectorAll('.local-time').forEach(function (element) {
            const utcString = element.getAttribute('data-utc');
            if (utcString) {
                const date = new Date(utcString);
                element.innerText = date.toLocaleString(undefined, {
                    timeZone: userTimezone,
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
            }
        });
    })();

});