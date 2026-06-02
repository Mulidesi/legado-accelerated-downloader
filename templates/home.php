<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Legado 资源加速下载</title>
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
    <button class="theme-toggle" id="themeToggle" aria-label="切换主题"></button>
    
    <div class="container">
        <header class="page-header">
            <h1>
                <img src="assets/favicon.ico" alt="Logo" width="40" height="40">
                Legado 资源加速下载
            </h1>
            <p class="text-center">本项目仅为聚合下载页面，应用版权归原作者所有</p>
            <?php if (!empty($marquee['enabled']) && !empty($marquee['items']) && is_array($marquee['items'])): ?>
                <div class="page-marquee" aria-label="站点公告">
                    <div class="page-marquee-track">
                        <?php foreach ($marquee['items'] as $item): ?>
                            <?php
                            $text = isset($item['text']) ? trim($item['text']) : '';
                            $url = isset($item['url']) ? trim($item['url']) : '';
                            $hasSafeUrl = $url !== '' && preg_match('/^https?:\/\//i', $url);
                            ?>
                            <?php if ($text !== ''): ?>
                                <?php if ($hasSafeUrl): ?>
                                    <a href="<?= h($url) ?>" target="_blank" rel="noopener noreferrer" class="page-marquee-item"><?= h($text) ?></a>
                                <?php else: ?>
                                    <span class="page-marquee-item"><?= h($text) ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </header>
        
        <div class="filter-container">
            <button class="filter-btn active" data-platform="all">全部</button>
            <button class="filter-btn" data-platform="Android">Android</button>
            <button class="filter-btn" data-platform="iOS">iOS</button>
            <button class="filter-btn" data-platform="HarmonyOS">HarmonyOS</button>
            <button class="filter-btn" data-platform="Windows">Windows</button>
        </div>
        
        <div class="row">
            <?php if (empty($resources)): ?>
                <div class="col-12">
                    <div class="card p-4 text-center">
                        暂无资源配置，请编辑 data/resources.json 添加资源
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($resources as $resource): ?>
                    <div class="col-md-6 col-lg-4 resource-item" data-platforms='<?= json_encode($resource['platforms'] ?? []) ?>'>
                        <a href="index.php?owner=<?= urlencode($resource['owner']) ?>&repo=<?= urlencode($resource['repo']) ?>" class="resource-card-wrapper">
                            <div class="resource-card p-4 <?= $resource['recommended'] ?? false ? 'recommended' : '' ?>">
                                <h5 class="mb-3" style="display: flex; align-items: center; margin-bottom: 12px !important; flex: 0 0 auto; min-height: 32px;">
                                    <img src="assets/github-icon.png" alt="GitHub" width="24" height="24" style="flex-shrink: 0; margin-right: 8px;">
                                    <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; min-width: 0;"><?= h($resource['name']) ?></span>
                                </h5>
                                <div class="resource-description mb-3" style="min-height: 60px; max-height: 80px; overflow-y: auto; margin-bottom: 12px !important; flex: 0 0 auto;">
                                    <?= nl2br(h($resource['description'])) ?>
                                </div>
                                <div style="margin-bottom: 12px; flex: 0 0 auto;">
                                    <?php if (!empty($resource['platforms'])): ?>
                                        <?php foreach ($resource['platforms'] as $platform): ?>
                                            <span class="platform-badge"><?= $platform ?></span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <div style="flex: 1 1 auto;"></div>
                                <div>
                                    <span class="btn btn-primary">
                                        查看详情
                                    </span>
                                    <span class="badge <?= $resource['usePrerelease'] ? 'bg-warning' : 'bg-success' ?>" style="float: right; margin-top: 8px; opacity: 0.85;">
                                        <?= $resource['usePrerelease'] ? '预发布' : '正式版' ?>
                                    </span>
                                </div>
                                <div class="mt-auto" style="border-top: 1px solid var(--md-sys-color-outline); opacity: 0.6; padding-top: 12px; margin-top: 12px !important;">
                                    <small>
                                        <img src="assets/github-icon.png" alt="GitHub" width="14" height="14" class="me-1" style="vertical-align: text-bottom;">
                                        <?= h($resource['owner'] . '/' . $resource['repo']) ?>
                                    </small>
                                    <?php if (!empty($resource['updatedAt'])): ?>
                                        <small class="resource-updated">最近更新：<?= h(formatDate($resource['updatedAt'])) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <footer class="mt-5">
            <div class="glass d-inline-block px-4 py-2" style="border-radius: 20px; background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px);">
                <p class="mb-0" style="font-size: 14px; color: var(--md-sys-color-on-surface-variant);">
                    由第三方 GitHub 加速服务提供支持 | 
                    <a href="https://github.com" target="_blank" rel="noopener noreferrer" style="color: var(--md-sys-color-primary);">GitHub</a>
                </p>
            </div>
        </footer>
    </div>
    
    <script>
        (function() {
            const filterBtns = document.querySelectorAll('.filter-btn');
            const resourceItems = Array.from(document.querySelectorAll('.resource-item')).map(item => ({
                el: item,
                platforms: JSON.parse(item.dataset.platforms || '[]')
            }));
            
            filterBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const platform = this.dataset.platform;
                    
                    filterBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    resourceItems.forEach(item => {
                        if (platform === 'all' || item.platforms.includes(platform)) {
                            item.el.style.display = '';
                        } else {
                            item.el.style.display = 'none';
                        }
                    });
                });
            });
        })();
    </script>
    <script src="assets/theme-switcher.js"></script>
</body>
</html>
