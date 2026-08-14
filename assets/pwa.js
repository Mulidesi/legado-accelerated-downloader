(() => {
    'use strict';

    if (!('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('./sw.js', { scope: './' }).catch(() => {
            // 注册失败不影响页面核心下载功能，保持静默降级。
        });
    }, { once: true });
})();
