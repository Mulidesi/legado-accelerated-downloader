<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($resource['name']) ?> - Legado 资源加速下载</title>
    <link rel="icon" href="../assets/favicon.ico" type="image/x-icon">
    <script>
        (function() {
            const saved = localStorage.getItem('gh-accel-theme');
            const theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            if (theme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
    <link href="../assets/material-theme.css" rel="stylesheet">
</head>
<body>
    <button class="theme-toggle" id="themeToggle" aria-label="切换主题"></button>
    
    <div class="container">
        <header class="page-header">
            <h1>
                <img src="../assets/favicon.ico" alt="Logo" width="40" height="40">
                Legado 资源加速下载
            </h1>
            <p class="text-center">本项目仅为聚合下载页面，应用版权归原作者所有</p>
        </header>
        
        <a href="index.php" class="btn back-btn mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/>
            </svg>
            返回列表
        </a>
        
        <div class="card p-4 mb-4">
            <h5 class="mb-3">资源简介</h5>
            <p style="line-height: 1.6; margin-bottom: 16px;"><?= nl2br(h($resource['description'])) ?></p>
            
            <div class="stats-container mb-3">
                <?php if ($repoInfo): ?>
                    <div class="stat-item">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 .25a.75.75 0 0 1 .673.418l1.882 3.815 4.21.612a.75.75 0 0 1 .416 1.279l-3.046 2.97.719 4.192a.75.75 0 0 1-1.088.791L8 12.347l-3.766 1.98a.75.75 0 0 1-1.088-.79l.72-4.194L.818 6.374a.75.75 0 0 1 .416-1.28l4.21-.611L7.327.668A.75.75 0 0 1 8 .25z"/>
                        </svg>
                        <?= number_format($repoInfo['stargazers_count']) ?> Stars
                    </div>
                    <div class="stat-item">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 3a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"/>
                            <path d="m5.93 6.704-.847 10.816a.75.75 0 0 0 1.492.117L8 3.251l1.425 14.384a.75.75 0 0 0 1.492-.117L10.07 6.704A4.483 4.483 0 0 1 8 7a4.49 4.49 0 0 1-2.07-.296zM3.5 3.75a.5.5 0 0 1 .5-.5H8a.5.5 0 0 1 0 1H4a.5.5 0 0 1-.5-.5z"/>
                        </svg>
                        <?= number_format($repoInfo['forks_count']) ?> Forks
                    </div>
                <?php endif; ?>
            </div>
            
            <a href="https://github.com/<?= h($resource['owner']) ?>/<?= h($resource['repo']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary">
                <img src="../assets/github-icon.png" alt="GitHub" width="18" height="18">
                访问 GitHub 仓库
            </a>
        </div>
        
        <h4 class="mb-4">最近版本</h4>
        
        <?php if (isset($releases['error'])): ?>
            <div class="alert alert-<?= $releases['error'] === 'ratelimit' ? 'warning' : 'danger' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="me-2" viewBox="0 0 16 16">
                    <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                </svg>
                <?= h($releases['message']) ?>
            </div>
        <?php elseif (empty($releases)): ?>
            <div class="alert alert-info">暂无 release 版本</div>
        <?php else: ?>
            <div class="releases-container">
                <?php foreach ($releases as $release): ?>
                <div class="release-card-wrapper">
                    <div class="release-card p-4 mb-4">
                            <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
                                <div>
                                    <span class="release-tag"><?= h($release['tag_name']) ?></span>
                                    <?php if ($release['prerelease'] ?? false): ?>
                                        <span class="badge bg-warning ms-1">预发布</span>
                                    <?php elseif ($release['_isLatest'] ?? false): ?>
                                        <span class="badge bg-success ms-1">Latest</span>
                                    <?php endif; ?>
                                    <h5 class="mt-2 mb-0"><?= h($release['name'] ?: $release['tag_name']) ?></h5>
                                </div>
                                <small style="display: flex; align-items: center; gap: 4px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/>
                                        <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/>
                                    </svg>
                                    <?= formatDate($release['published_at']) ?>
                                </small>
                            </div>
                            
                            <div class="release-body" id="body-<?= $release['id'] ?>">
                                <?php
                                $body = $release['body'] ?? '';
                                echo nl2br(h($body));
                                ?>
                            </div>
                            
                                <?php if (!empty($release['assets'])): ?>
                                <div class="mt-4">
                                    <h6 class="mb-3">下载资源</h6>
                                    <?php foreach ($release['assets'] as $asset): ?>
                                        <?php
                                        $filename = $asset['name'];
                                        $fileSize = formatFileSizeOptimized($asset['size']);
                                        $downloadUrl = buildAcceleratedUrlOptimized($proxyUrls, $asset['browser_download_url']);
                                        ?>
                                        <div class="asset-item" onclick="downloadFile('<?= h($downloadUrl) ?>', '<?= h($resource['owner']) ?>', '<?= h($resource['repo']) ?>', '<?= h($filename) ?>')">
                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16" style="flex-shrink: 0; color: var(--md-sys-color-primary);">
                                                    <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                                                    <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                                                </svg>
                                                <div style="flex: 1; min-width: 0;">
                                                    <div style="overflow-x: auto; overflow-y: hidden; white-space: nowrap; scrollbar-width: none; -ms-overflow-style: none;">
                                                        <strong><?= h($filename) ?></strong>
                                                    </div>
                                                </div>
                                                <div style="background-color: rgba(255, 255, 255, 0.2); padding: 4px 12px; border-radius: 12px; flex-shrink: 0;" class="file-size">
                                                    <small style="color: var(--md-sys-color-on-surface-variant); font-size: 12px;"><?= $fileSize ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-secondary mt-3 mb-0">
                                    <small>该版本没有附加资源文件</small>
                                </div>
                            <?php endif; ?>
                        </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <footer class="mt-5">
            <div class="glass d-inline-block px-4 py-2" style="border-radius: 20px; background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px);">
                <p class="mb-0" style="font-size: 14px; color: var(--md-sys-color-on-surface-variant);">
                    由第三方 GitHub 加速服务提供支持 | 
                    <a href="https://github.com/Mulidesi/legado-accelerated-downloader" target="_blank" rel="noopener noreferrer" style="color: var(--md-sys-color-primary);">GitHub</a>
                </p>
            </div>
        </footer>
    </div>
    
    <script>
        function downloadFile(url, owner, repo, filename) {
            window.open(url, '_blank');
        }
    </script>
    <script src="../assets/theme-switcher.js"></script>
</body>
</html>
