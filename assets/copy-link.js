/**
 * 下载链接一键复制
 *
 * 采用事件委托绑定在 document 上，因此同时适用于：
 *   1. 首页侧栏异步注入的详情片段（innerHTML 替换后无需重新绑定）
 *   2. 独立详情页直出的内容
 */

(function () {
    'use strict';

    var FEEDBACK_MS = 2000;
    var timers = new WeakMap();

    /**
     * 复制文本到剪贴板
     * navigator.clipboard 仅在安全上下文（HTTPS/localhost）可用，
     * 非安全上下文回退到 execCommand 方案。
     */
    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }

        return new Promise(function (resolve, reject) {
            var ta = document.createElement('textarea');
            ta.value = text;
            // 移出视口而非 display:none，否则无法选中
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.top = '-9999px';
            ta.style.opacity = '0';
            document.body.appendChild(ta);

            try {
                ta.select();
                ta.setSelectionRange(0, ta.value.length);
                var ok = document.execCommand('copy');
                document.body.removeChild(ta);
                ok ? resolve() : reject(new Error('execCommand 复制失败'));
            } catch (err) {
                document.body.removeChild(ta);
                reject(err);
            }
        });
    }

    function showFeedback(btn, success) {
        var label = success ? '已复制' : '复制失败';
        btn.classList.add('copied');
        btn.setAttribute('aria-label', label);
        btn.setAttribute('title', label);

        // 同一按钮连续点击时重置计时，避免反馈提前消失
        var prev = timers.get(btn);
        if (prev) {
            clearTimeout(prev);
        }

        timers.set(btn, setTimeout(function () {
            btn.classList.remove('copied');
            btn.setAttribute('aria-label', '复制下载链接');
            btn.setAttribute('title', '复制链接');
            timers.delete(btn);
        }, FEEDBACK_MS));
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('.asset-copy') : null;
        if (!btn) {
            return;
        }

        // 按钮与下载链接是兄弟节点，此处阻止冒泡仅为保险
        e.preventDefault();
        e.stopPropagation();

        var url = btn.dataset.url;
        if (!url) {
            return;
        }

        copyText(url)
            .then(function () {
                showFeedback(btn, true);
            })
            .catch(function () {
                showFeedback(btn, false);
            });
    });
})();
