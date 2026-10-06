document.addEventListener('DOMContentLoaded', () => {
    const banner = document.getElementById('cookie-banner');
    const acceptBtn = document.getElementById('accept-cookies');

    if (banner && !document.cookie.includes('cookie_consent=true')) {
        banner.style.display = 'block';
    }

    if (acceptBtn && banner) {
        acceptBtn.addEventListener('click', () => {
            document.cookie = "cookie_consent=true; max-age=31536000; path=/";
            banner.style.display = 'none';
        });
    }
});
