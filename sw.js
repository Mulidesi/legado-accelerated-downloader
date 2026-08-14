/**
 * Service Worker - 离线缓存
 *
 * 缓存策略：
 * - 静态资源（CSS/JS/图标）：cache-first，命中即返回，后台不更新（版本号变更时整体换缓存）
 * - HTML 页面：network-first，失败时回退缓存，保证内容新鲜
 * - fragment 接口与 GitHub 数据：不缓存，避免展示过期的版本信息
 *
 * 版本号变更后旧缓存会在 activate 阶段清理。修改静态资源时需同步递增 CACHE_VERSION。
 */

const CACHE_VERSION = 'v1';
const CACHE_PREFIX = 'legado-';
const STATIC_CACHE = CACHE_PREFIX + 'static-' + CACHE_VERSION;
const PAGE_CACHE = CACHE_PREFIX + 'pages-' + CACHE_VERSION;

// 预缓存的静态资源（安装时写入，缺失任一项不阻断安装）
const PRECACHE_URLS = [
    './',
    './index.php',
    './assets/material-theme.css',
    './assets/app.js',
    './assets/copy-link.js',
    './assets/theme-switcher.js',
    './assets/pwa.js',
    './assets/logo.png',
    './assets/logo@2x.png',
    './assets/github-icon.png',
    './assets/favicon.ico',
    './assets/icon-192.png',
    './manifest.webmanifest'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) => {
            // 逐个添加：单个 404 不会导致整体 install 失败
            return Promise.allSettled(
                PRECACHE_URLS.map((url) => cache.add(new Request(url, { cache: 'reload' })))
            );
        }).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            // 仅清理本应用前缀下的旧版本缓存，保留同源其他应用的数据
            keys.filter((key) => key.startsWith(CACHE_PREFIX) && key !== STATIC_CACHE && key !== PAGE_CACHE)
                .map((key) => caches.delete(key))
        )).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    // 仅处理 GET，其他方法直接放行
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);
    const scopeUrl = new URL(self.registration.scope);
    const relativePath = url.pathname.startsWith(scopeUrl.pathname)
        ? url.pathname.slice(scopeUrl.pathname.length)
        : url.pathname.replace(/^\//, '');

    // 跨域请求（GitHub API、加速代理）不介入
    if (url.origin !== self.location.origin) {
        return;
    }

    // fragment 接口与健康检查始终走网络：内容随上游变化，缓存会造成版本信息过期
    if (url.searchParams.get('fragment') === '1' || relativePath === 'health') {
        return;
    }

    // 静态资源：cache-first；relativePath 兼容根目录和子目录部署
    if (relativePath.startsWith('assets/') || relativePath.endsWith('.webmanifest')) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) {
                    return cached;
                }
                return fetch(request).then((response) => {
                    if (response.ok) {
                        const copy = response.clone();
                        caches.open(STATIC_CACHE).then((cache) => cache.put(request, copy));
                    }
                    return response;
                });
            })
        );
        return;
    }

    // 页面仅处理浏览器导航请求，避免任意查询参数将 Cache Storage 无限撑大
    if (request.mode !== 'navigate') {
        return;
    }

    // HTML 页面：network-first，离线时回退缓存；最多保留 30 个页面
    event.respondWith(
        fetch(request).then((response) => {
            if (response.ok) {
                const copy = response.clone();
                caches.open(PAGE_CACHE).then(async (cache) => {
                    await cache.put(request, copy);
                    const keys = await cache.keys();
                    if (keys.length > 30) {
                        await Promise.all(keys.slice(0, keys.length - 30).map((key) => cache.delete(key)));
                    }
                });
            }
            return response;
        }).catch(() => {
            return caches.match(request).then((cached) => {
                if (cached) {
                    return cached;
                }
                // 无缓存时回退到首页，避免显示浏览器默认错误页
                return caches.match('./index.php');
            });
        })
    );
});
