<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($resource['name']) ?> - Legado 资源加速下载</title>
    <meta name="description" content="<?= h(mb_substr(trim(preg_replace('/\s+/u', ' ', $resource['description'] ?? '')), 0, 120)) ?>">
    <meta name="theme-color" content="#5B5BD6">
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <script>
        (function() {
            const saved = localStorage.getItem('gh-accel-theme');
            const theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            if (theme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
    <link href="assets/material-theme.css" rel="stylesheet">
</head>
<body>
    <a href="#main-content" class="skip-link">跳转到主内容</a>

    <nav class="nav">
        <div class="container nav-inner">
            <a href="index.php" class="nav-brand">
                <img src="assets/favicon.ico" alt="" width="26" height="26">
                <span>Legado 资源加速下载</span>
            </a>
            <div class="nav-actions">
                <button class="icon-btn" id="themeToggle" type="button" aria-label="切换深色/浅色主题"></button>
            </div>
        </div>
    </nav>

    <div class="container">
        <main class="page-detail" id="main-content">
            <a href="index.php" class="back-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                    <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/>
                </svg>
                返回资源列表
            </a>

            <div class="page-detail-head">
                <h1><?= h($resource['name']) ?></h1>
            </div>

            <?php include TEMPLATES_DIR . '/detail-fragment.php'; ?>
        </main>

        <footer class="footer">
            由第三方 GitHub 加速服务提供支持
            <span class="footer-sep">·</span>
            <a href="https://github.com/Mulidesi/legado-accelerated-downloader" target="_blank" rel="noopener noreferrer">项目源码</a>
        </footer>
    </div>

    <script src="assets/theme-switcher.js"></script>
</body>
</html>
