<?php
/**
 * 资源详情片段
 * 同一份标记同时服务于：
 *   1. 首页侧栏的异步加载（fragment=1）
 *   2. 独立详情页直出（无 JS / 直链 / SEO 回退）
 *
 * 依赖上下文变量：$resource, $repoInfo, $releases, $sourceType, $proxyUrls
 */

if (!defined('DATA_DIR')) {
    exit;
}

$repoFull = $resource['owner'] . '/' . $resource['repo'];
$hasStats = $repoInfo && (isset($repoInfo['stargazers_count']) || isset($repoInfo['forks_count']));
$description = trim($resource['description'] ?? '');
?>
<div class="detail-meta">
    <span class="chip chip-mono"><?= h($repoFull) ?></span>
    <span class="chip <?= !empty($resource['usePrerelease']) ? 'chip-warning' : 'chip-success' ?>">
        <?= !empty($resource['usePrerelease']) ? '预发布' : '正式版' ?>
    </span>
    <?php if (!empty($resource['platforms'])): ?>
        <?php foreach ($resource['platforms'] as $platform): ?>
            <span class="chip"><?= h($platform) ?></span>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($description !== ''): ?>
    <p class="detail-desc"><?= nl2br(h($description)) ?></p>
<?php endif; ?>

<?php if ($hasStats): ?>
    <div class="detail-stats">
        <span class="stat">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                <path d="M8 .25a.75.75 0 0 1 .673.418l1.882 3.815 4.21.612a.75.75 0 0 1 .416 1.279l-3.046 2.97.719 4.192a.75.75 0 0 1-1.088.791L8 12.347l-3.766 1.98a.75.75 0 0 1-1.088-.79l.72-4.194L.818 6.374a.75.75 0 0 1 .416-1.28l4.21-.611L7.327.668A.75.75 0 0 1 8 .25z"/>
            </svg>
            <?= number_format($repoInfo['stargazers_count'] ?? 0) ?> Stars
        </span>
        <span class="stat">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                <path d="M8 3a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"/>
                <path d="m5.93 6.704-.847 10.816a.75.75 0 0 0 1.492.117L8 3.251l1.425 14.384a.75.75 0 0 0 1.492-.117L10.07 6.704A4.483 4.483 0 0 1 8 7a4.49 4.49 0 0 1-2.07-.296zM3.5 3.75a.5.5 0 0 1 .5-.5H8a.5.5 0 0 1 0 1H4a.5.5 0 0 1-.5-.5z"/>
            </svg>
            <?= number_format($repoInfo['forks_count'] ?? 0) ?> Forks
        </span>
    </div>
<?php endif; ?>

<div class="detail-actions">
    <a href="https://github.com/<?= h($resource['owner']) ?>/<?= h($resource['repo']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-ghost">
        <img src="assets/github-icon.png" alt="" width="16" height="16" loading="lazy">
        访问 GitHub 仓库
    </a>
</div>

<div class="section-title"><?= $sourceType === 'tag' ? '最近 Tag' : '最近版本' ?></div>

<?php if (isset($releases['error'])): ?>
    <div class="alert alert-<?= $releases['error'] === 'ratelimit' ? 'warning' : 'danger' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
            <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
        </svg>
        <span><?= h($releases['message']) ?></span>
    </div>
<?php elseif (empty($releases)): ?>
    <div class="alert alert-info">
        <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
            <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
        </svg>
        <span>暂无发布版本</span>
    </div>
<?php else: ?>
    <?php foreach ($releases as $release): ?>
        <?php
        $body = trim($release['body'] ?? '');
        $releaseTitle = $release['name'] ?: $release['tag_name'];
        $hasAssets = !empty($release['assets']);
        $hasSource = !empty($release['zipball_url']) || !empty($release['tarball_url']);
        ?>
        <div class="release">
            <div class="release-head">
                <div class="release-head-main">
                    <div class="release-tags">
                        <span class="chip chip-mono chip-accent"><?= h($release['tag_name']) ?></span>
                        <?php if ($release['prerelease'] ?? false): ?>
                            <span class="chip chip-warning">预发布</span>
                        <?php elseif ($release['_isLatest'] ?? false): ?>
                            <span class="chip chip-success">Latest</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($releaseTitle !== $release['tag_name']): ?>
                        <div class="release-name"><?= h($releaseTitle) ?></div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($release['published_at'])): ?>
                    <span class="release-date">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                            <path d="M8 3.5a.5.5 0 0 0-1 0V8a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 7.71V3.5z"/>
                            <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/>
                        </svg>
                        <?= h(formatDate($release['published_at'])) ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($body !== ''): ?>
                <details class="notes">
                    <summary>版本说明</summary>
                    <div class="notes-body"><?= h($body) ?></div>
                </details>
            <?php endif; ?>

            <?php if ($hasAssets): ?>
                <div class="asset-list">
                    <?php foreach ($release['assets'] as $asset): ?>
                        <a class="asset" href="<?= h(buildAcceleratedUrlOptimized($proxyUrls, $asset['browser_download_url'])) ?>" target="_blank" rel="noopener noreferrer">
                            <svg class="asset-icon" xmlns="http://www.w3.org/2000/svg" width="17" height="17" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                                <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                            </svg>
                            <span class="asset-name" title="<?= h($asset['name']) ?>"><?= h($asset['name']) ?></span>
                            <span class="asset-size"><?= h(formatFileSizeOptimized($asset['size'])) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($hasSource): ?>
                <div class="asset-list">
                    <?php
                    $sourceLinks = array();
                    if (!empty($release['zipball_url'])) {
                        $sourceLinks['Source code (zip)'] = $release['zipball_url'];
                    }
                    if (!empty($release['tarball_url'])) {
                        $sourceLinks['Source code (tar.gz)'] = $release['tarball_url'];
                    }
                    ?>
                    <?php foreach ($sourceLinks as $label => $url): ?>
                        <a class="asset" href="<?= h(buildAcceleratedUrlOptimized($proxyUrls, $url)) ?>" target="_blank" rel="noopener noreferrer">
                            <svg class="asset-icon" xmlns="http://www.w3.org/2000/svg" width="17" height="17" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                                <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                            </svg>
                            <span class="asset-name"><?= h($label) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-muted">
                    <span>该版本没有附加资源文件</span>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
