<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Legado 资源加速下载</title>
    <meta name="description" content="Legado 开源阅读相关资源的聚合下载页，提供多个分支版本的加速下载入口。">
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
                <a class="icon-btn" href="https://github.com/Mulidesi/legado-accelerated-downloader" target="_blank" rel="noopener noreferrer" aria-label="查看项目源码">
                    <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                        <path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-2.91-.88-2.91-2.77 0-.79.28-1.44.75-1.95-.07-.18-.33-.94.07-1.95 0 0 .61-.19 2.01.75a6.9 6.9 0 0 1 1.83-.25c.62 0 1.25.08 1.83.25 1.4-.95 2.01-.75 2.01-.75.4 1.01.15 1.77.07 1.95.47.51.75 1.16.75 1.95 0 1.9-1.13 2.57-2.92 2.77.29.25.55.74.55 1.5 0 1.08-.01 1.96-.01 2.23 0 .21.15.46.55.38A7.99 7.99 0 0 0 16 8c0-4.42-3.58-8-8-8z"/>
                    </svg>
                </a>
                <button class="icon-btn" id="themeToggle" type="button" aria-label="切换深色/浅色主题"></button>
            </div>
        </div>
    </nav>

    <header class="container hero">
        <h1>Legado 资源加速下载</h1>
        <p>聚合 Legado 及相关分支的发布版本，通过第三方加速节点提供下载入口。应用版权归原作者所有。</p>

        <?php if (!empty($marquee['enabled']) && !empty($marquee['items']) && is_array($marquee['items'])): ?>
            <?php ob_start(); ?>
            <?php foreach ($marquee['items'] as $item): ?>
                <?php
                $text = isset($item['text']) ? trim($item['text']) : '';
                $url = isset($item['url']) ? trim($item['url']) : '';
                $hasSafeUrl = $url !== '' && preg_match('/^https?:\/\//i', $url);
                ?>
                <?php if ($text !== ''): ?>
                    <?php if ($hasSafeUrl): ?>
                        <a href="<?= h($url) ?>" target="_blank" rel="noopener noreferrer" class="notice-item"><?= h($text) ?></a>
                    <?php else: ?>
                        <span class="notice-item"><?= h($text) ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php $marqueeContent = ob_get_clean(); ?>
            <?php if (trim($marqueeContent) !== ''): ?>
                <div class="notice" aria-label="站点公告">
                    <svg class="notice-icon" xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                        <path d="M13 2.5a1.5 1.5 0 0 1 3 0v11a1.5 1.5 0 0 1-3 0v-.214c-2.162-1.241-4.49-1.843-6.912-2.083l.405 2.712A1 1 0 0 1 5.51 15.1h-.548a1 1 0 0 1-.916-.599L2.85 10.955A1.008 1.008 0 0 1 2 9.957V6.043a1 1 0 0 1 .85-.988l1.196-3.546A1 1 0 0 1 4.962.91h.548a1 1 0 0 1 .983 1.185l-.405 2.712C8.51 4.567 10.838 3.965 13 2.724V2.5z"/>
                    </svg>
                    <div class="notice-viewport">
                        <div class="notice-track">
                            <?= $marqueeContent ?>
                            <?= $marqueeContent ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </header>

    <?php
    // 从实际资源数据中汇总可用平台，避免出现永远筛不到结果的按钮
    $availablePlatforms = array();
    foreach ($resources as $r) {
        if (!empty($r['platforms']) && is_array($r['platforms'])) {
            foreach ($r['platforms'] as $p) {
                $availablePlatforms[$p] = true;
            }
        }
    }
    $platformOrder = array('Android', 'iOS', 'HarmonyOS', 'Windows', 'macOS', 'Linux');
    $filterPlatforms = array();
    foreach ($platformOrder as $p) {
        if (isset($availablePlatforms[$p])) {
            $filterPlatforms[] = $p;
            unset($availablePlatforms[$p]);
        }
    }
    $filterPlatforms = array_merge($filterPlatforms, array_keys($availablePlatforms));
    $totalCount = count($resources);
    ?>

    <div class="toolbar">
        <div class="container toolbar-inner">
            <div class="search">
                <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                    <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
                </svg>
                <input type="search" id="search-input" placeholder="搜索资源名称或仓库" aria-label="搜索资源" autocomplete="off">
                <button class="search-clear" id="search-clear" type="button" aria-label="清除搜索" hidden>
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                        <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
                    </svg>
                </button>
            </div>

            <div class="filters" role="group" aria-label="按平台筛选">
                <button class="filter-btn active" type="button" data-platform="all" aria-pressed="true">全部</button>
                <?php foreach ($filterPlatforms as $platform): ?>
                    <button class="filter-btn" type="button" data-platform="<?= h($platform) ?>" aria-pressed="false"><?= h($platform) ?></button>
                <?php endforeach; ?>
            </div>

            <span class="result-count" id="result-count"><?= $totalCount ?> 个资源</span>
        </div>
    </div>

    <div class="sr-only" aria-live="polite" id="announcer"></div>

    <main class="container" id="main-content">
        <?php if (empty($resources)): ?>
            <div class="empty-state">
                <div class="empty-state-title">暂无资源</div>
                <p>请编辑 data/resources.json 添加资源配置。</p>
            </div>
        <?php else: ?>
            <div class="grid" id="resource-grid">
                <?php foreach ($resources as $resource): ?>
                    <?php $detailUrl = 'index.php?owner=' . urlencode($resource['owner']) . '&repo=' . urlencode($resource['repo']); ?>
                    <a class="card <?= !empty($resource['recommended']) ? 'recommended' : '' ?>"
                       href="<?= h($detailUrl) ?>"
                       data-owner="<?= h($resource['owner']) ?>"
                       data-repo="<?= h($resource['repo']) ?>"
                       data-name="<?= h($resource['name']) ?>"
                       data-platforms='<?= h(json_encode($resource['platforms'] ?? array(), JSON_UNESCAPED_UNICODE)) ?>'
                       data-search="<?= h(mb_strtolower($resource['name'] . ' ' . $resource['owner'] . '/' . $resource['repo'])) ?>">
                        <div class="card-top">
                            <span class="card-title" title="<?= h($resource['name']) ?>"><?= h($resource['name']) ?></span>
                            <?php if (!empty($resource['recommended'])): ?>
                                <span class="chip chip-accent">推荐</span>
                            <?php endif; ?>
                        </div>

                        <?php if (trim($resource['description'] ?? '') !== ''): ?>
                            <p class="card-desc"><?= h($resource['description']) ?></p>
                        <?php endif; ?>

                        <div class="card-chips">
                            <span class="chip <?= !empty($resource['usePrerelease']) ? 'chip-warning' : 'chip-success' ?>">
                                <?= !empty($resource['usePrerelease']) ? '预发布' : '正式版' ?>
                            </span>
                            <?php if (!empty($resource['platforms'])): ?>
                                <?php foreach ($resource['platforms'] as $platform): ?>
                                    <span class="chip"><?= h($platform) ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="chip">平台待识别</span>
                            <?php endif; ?>
                        </div>

                        <div class="card-spacer"></div>

                        <div class="card-foot">
                            <span class="card-repo">
                                <img src="assets/github-icon.png" alt="" width="13" height="13" loading="lazy">
                                <?= h($resource['owner'] . '/' . $resource['repo']) ?>
                            </span>
                            <?php if (!empty($resource['updatedAt'])): ?>
                                <span class="card-date"><?= h(formatDate($resource['updatedAt'])) ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="empty-state" id="empty-state" hidden>
                <div class="empty-state-title">没有匹配的资源</div>
                <p>试试更换关键词，或切换到「全部」平台。</p>
            </div>
        <?php endif; ?>
    </main>

    <footer class="footer">
        由第三方 GitHub 加速服务提供支持
        <span class="footer-sep">·</span>
        <a href="https://github.com/Mulidesi/legado-accelerated-downloader" target="_blank" rel="noopener noreferrer">项目源码</a>
    </footer>

    <div class="sheet-backdrop" id="sheet-backdrop" hidden></div>
    <aside class="sheet" id="sheet" role="dialog" aria-modal="true" aria-labelledby="sheet-title" hidden>
        <div class="sheet-head">
            <div class="sheet-head-main">
                <div class="sheet-eyebrow">版本与下载</div>
                <h2 class="sheet-title" id="sheet-title"></h2>
            </div>
            <button class="icon-btn" id="sheet-close" type="button" aria-label="关闭详情">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                    <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
                </svg>
            </button>
        </div>
        <div class="sheet-body" id="sheet-body"></div>
    </aside>

    <script src="assets/app.js"></script>
    <script src="assets/theme-switcher.js"></script>
</body>
</html>
