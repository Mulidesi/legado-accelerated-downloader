<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Legado 资源加速下载</title>
    <meta name="description" content="Legado 开源阅读相关资源的聚合下载页，提供多个分支版本的加速下载入口。">
    <meta name="theme-color" content="#3B82F6">
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
    <script nonce="<?= $cspNonce ?>">
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
                <img src="assets/logo.png" srcset="assets/logo.png 1x, assets/logo@2x.png 2x" alt="" width="36" height="26">
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

    <div class="container custom-download">
        <div class="custom-download-inner">
            <label for="custom-repo-input" class="custom-download-label">自定义仓库加速下载</label>
            <div class="custom-download-row">
                <input type="text" 
                       id="custom-repo-input" 
                       class="custom-repo-input" 
                       placeholder="输入 owner/repo 或 https://github.com/owner/repo" 
                       autocomplete="off"
                       aria-describedby="custom-repo-error">
                <button type="button" id="custom-repo-submit" class="custom-repo-submit">获取下载</button>
            </div>
            <div id="custom-repo-error" class="custom-repo-error" role="alert" aria-live="assertive"></div>
        </div>
    </div>

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
    
    // 分类汇总：优先使用配置的 categories，为空时从资源动态汇总
    $filterCategories = array();
    if (!empty($config['categories']) && is_array($config['categories'])) {
        $filterCategories = $config['categories'];
    } else {
        $categorySeen = array();
        foreach ($resources as $r) {
            $cat = isset($r['category']) && trim($r['category']) !== '' ? trim($r['category']) : '未分类';
            if (!isset($categorySeen[$cat])) {
                $categorySeen[$cat] = true;
                if ($cat !== '未分类') {
                    $filterCategories[] = $cat;
                }
            }
        }
        if (isset($categorySeen['未分类'])) {
            $filterCategories[] = '未分类';
        }
    }
    ?>

    <div class="toolbar">
        <div class="container toolbar-inner">
            <div class="search">
                <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                    <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
                </svg>
                <input type="search" id="search-input" placeholder="搜索资源名称或仓库" aria-label="搜索资源" title="快捷键：Ctrl+K 或 /" autocomplete="off">
                <button class="search-clear" id="search-clear" type="button" aria-label="清除搜索" hidden>
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                        <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
                    </svg>
                </button>
            </div>

            <div class="filters" role="group" aria-label="按平台筛选">
                <button class="filter-btn active" type="button" data-platform="all" aria-pressed="true">
                    全部<span class="filter-count" data-count-platform="all"><?= $totalCount ?></span>
                </button>
                <?php foreach ($filterPlatforms as $platform): ?>
                    <?php
                    $platformCount = 0;
                    foreach ($resources as $r) {
                        if (!empty($r['platforms']) && in_array($platform, $r['platforms'])) {
                            $platformCount++;
                        }
                    }
                    ?>
                    <button class="filter-btn" type="button" data-platform="<?= h($platform) ?>" aria-pressed="false">
                        <?= h($platform) ?><span class="filter-count" data-count-platform="<?= h($platform) ?>"><?= $platformCount ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($filterCategories)): ?>
                <div class="filters" role="group" aria-label="按分类筛选">
                    <button class="filter-btn category-filter active" type="button" data-category="all" aria-pressed="true">
                        全部<span class="filter-count" data-count-category="all"><?= $totalCount ?></span>
                    </button>
                    <?php foreach ($filterCategories as $category): ?>
                        <?php
                        $categoryCount = 0;
                        foreach ($resources as $r) {
                            $rCat = isset($r['category']) && trim($r['category']) !== '' ? trim($r['category']) : '未分类';
                            if ($rCat === $category) {
                                $categoryCount++;
                            }
                        }
                        ?>
                        <button class="filter-btn category-filter" type="button" data-category="<?= h($category) ?>" aria-pressed="false">
                            <?= h($category) ?><span class="filter-count" data-count-category="<?= h($category) ?>"><?= $categoryCount ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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
            <?php
            // 按分类分组资源，保持 filterCategories 的顺序
            $groupedResources = array();
            foreach ($filterCategories as $cat) {
                $groupedResources[$cat] = array();
            }
            foreach ($resources as $r) {
                $cat = isset($r['category']) && trim($r['category']) !== '' ? trim($r['category']) : '未分类';
                if (!isset($groupedResources[$cat])) {
                    $groupedResources[$cat] = array();
                }
                $groupedResources[$cat][] = $r;
            }
            // 移除空分组
            foreach (array_keys($groupedResources) as $cat) {
                if (empty($groupedResources[$cat])) {
                    unset($groupedResources[$cat]);
                }
            }
            ?>
            <div id="resource-grid">
            <?php foreach ($groupedResources as $categoryName => $categoryResources): ?>
            <section class="category-group" data-category-group="<?= h($categoryName) ?>">
                <h2 class="category-group-title"><?= h($categoryName) ?><span class="category-group-count"><?= count($categoryResources) ?></span></h2>
                <div class="grid">
                <?php foreach ($categoryResources as $resource): ?>
                    <?php $detailUrl = 'index.php?owner=' . urlencode($resource['owner']) . '&repo=' . urlencode($resource['repo']); ?>
                    <article class="card <?= !empty($resource['recommended']) ? 'recommended' : '' ?>"
                       data-owner="<?= h($resource['owner']) ?>"
                       data-repo="<?= h($resource['repo']) ?>"
                       data-name="<?= h($resource['name']) ?>"
                       data-category="<?= h($categoryName) ?>"
                       data-platforms='<?= h(json_encode($resource['platforms'] ?? array(), JSON_UNESCAPED_UNICODE)) ?>'
                       data-search="<?= h(utf8Lower($resource['name'] . ' ' . $resource['owner'] . '/' . $resource['repo'])) ?>">
                        <div class="card-top">
                            <h2 class="card-title" title="<?= h($resource['name']) ?>">
                                <a class="card-link" href="<?= h($detailUrl) ?>"><?= h($resource['name']) ?></a>
                            </h2>
                            <div class="card-badges">
                                <?php if (!empty($resource['recommended'])): ?>
                                    <span class="badge badge-recommended">推荐</span>
                                <?php endif; ?>
                                <span class="badge <?= !empty($resource['usePrerelease']) ? 'badge-prerelease' : 'badge-stable' ?>">
                                    <?= !empty($resource['usePrerelease']) ? '预发布' : '正式版' ?>
                                </span>
                            </div>
                        </div>

                        <div class="card-subline">
                            <a class="card-repo-link" href="<?= h('https://github.com/' . $resource['owner'] . '/' . $resource['repo']) ?>" target="_blank" rel="noopener noreferrer" aria-label="打开 GitHub 仓库 <?= h($resource['owner'] . '/' . $resource['repo']) ?>">
                                <img src="assets/github-icon.png" alt="" width="14" height="14" loading="lazy">
                                <span><?= h($resource['owner'] . '/' . $resource['repo']) ?></span>
                            </a>
                            <?php if (isset($resource['stats']) && is_array($resource['stats'])
                                && isset($resource['stats']['stars'], $resource['stats']['forks'])
                                && $resource['stats']['stars'] !== null && $resource['stats']['forks'] !== null): ?>
                                <?php $cardStats = $resource['stats']; ?>
                                <span class="card-stat" title="<?= $cardStats['stars'] ?> Stars">
                                        <svg class="card-stat-icon" xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                            <path d="M3.612 15.443c-.386.198-.824-.149-.746-.592l.83-4.73L.173 6.765c-.329-.314-.158-.888.283-.95l4.898-.696L7.538.792c.197-.39.73-.39.927 0l2.184 4.327 4.898.696c.441.062.612.636.282.95l-3.522 3.356.83 4.73c.078.443-.36.79-.746.592L8 13.187l-4.389 2.256z"/>
                                        </svg>
                                        <?= h(number_format($cardStats['stars'])) ?>
                                    </span>
                                    <span class="card-stat" title="<?= $cardStats['forks'] ?> Forks">
                                        <svg class="card-stat-icon" xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                            <path d="M5 3.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0zm0 2.122a2.25 2.25 0 1 0-1.5 0v.878A2.25 2.25 0 0 0 5.75 8.5h1.5v2.128a2.251 2.251 0 1 0 1.5 0V8.5h1.5a2.25 2.25 0 0 0 2.25-2.25v-.878a2.25 2.25 0 1 0-1.5 0v.878a.75.75 0 0 1-.75.75h-4.5A.75.75 0 0 1 5 6.25v-.878zm3.75 7.378a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0zm3-8.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5z"/>
                                        </svg>
                                        <?= h(number_format($cardStats['forks'])) ?>
                                    </span>
                            <?php endif; ?>
                        </div>

                        <?php if (trim($resource['description'] ?? '') !== ''): ?>
                            <p class="card-desc"><?= h($resource['description']) ?></p>
                        <?php endif; ?>

                        <div class="card-chips">
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
                            <div class="card-meta">
                                <?php if (isset($resource['stats']) && !empty($resource['stats']['stale'])): ?>
                                    <span class="card-stale" title="超过 30 天未更新">长期未更新</span>
                                <?php endif; ?>

                                <?php if (!empty($resource['releaseUpdatedAt'])): ?>
                                    <span class="card-release-date" title="最新 Release 发布时间：<?= h(formatDate($resource['releaseUpdatedAt'])) ?>">
                                        <svg class="card-release-icon" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                            <path d="M8 3.5a.75.75 0 0 1 .75.75v3.44l2.03 1.17a.75.75 0 0 1-.75 1.3l-2.4-1.38A.75.75 0 0 1 7.25 8V4.25A.75.75 0 0 1 8 3.5z"/>
                                            <path fill-rule="evenodd" d="M8 1.5a6.5 6.5 0 1 0 0 13 6.5 6.5 0 0 0 0-13zM3 8a5 5 0 1 1 10 0A5 5 0 0 1 3 8z"/>
                                        </svg>
                                        <span>Release <?= h(formatDate($resource['releaseUpdatedAt'])) ?></span>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
                </div>
            </section>
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
    <script src="assets/copy-link.js"></script>
    <script src="assets/theme-switcher.js"></script>
    <script src="assets/pwa.js"></script>
</body>
</html>
