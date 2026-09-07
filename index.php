<?php
/**
 * Legado 资源加速下载站点
 * v1.12.0 - 交互增强、PWA 支持与共享主机兼容性修复
 *
 * 安全改进:
 * - GitHub Token 从环境变量或本地配置读取
 * - resources.json 不再包含敏感信息
 * - 添加 .gitignore 防止敏感文件提交
 */

// 区分开发和生产环境。生产主机已关闭 log_errors，保留主机日志策略，
// 仅控制页面是否显示错误，避免通过 ini_set 改写虚拟主机配置。
if (getenv('APP_ENV') === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
}

// 使用主机 php.ini 中的 date.timezone（当前部署环境为 America/New_York）。
// 未配置时 PHP 会回退到 UTC，避免项目覆盖主机时区。

// 生成 CSP nonce
$cspNonce = base64_encode(random_bytes(16));

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header('Cache-Control: public, max-age=300');
// worker-src 'self' 供 Service Worker 使用；manifest-src 'self' 供 PWA manifest 使用
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$cspNonce}'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; worker-src 'self'; manifest-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'; upgrade-insecure-requests");

define('DATA_DIR', __DIR__ . '/data');
define('TEMPLATES_DIR', __DIR__ . '/templates');
define('CACHE_DIR', DATA_DIR . '/cache');

// 定义安全常量
define('MAX_INPUT_LENGTH', 500);
define('MAX_URL_LENGTH', 2048);
define('MAX_USERNAME_LENGTH', 100);
define('MAX_REPO_NAME_LENGTH', 100);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/batch-stats.php';

// 加载安全配置
$config = loadSecureConfig();
$GLOBALS['_legado_proxy_urls'] = isset($config['proxyUrls']) ? $config['proxyUrls'] : array();

if (!empty($config['legacyTokenInResources'])) {
    error_log('安全警告: GitHub Token 存储在 resources.json 中，建议迁移到环境变量或 config.local.json');
}

// 检查资源列表
$resources = isset($config['resources']) ? $config['resources'] : array();

if (empty($resources)) {
    http_response_code(500);
    exit('<h1>配置错误</h1><p>resources.json 不存在或格式错误，请确保包含 resources 数组</p>');
}

if (function_exists('ensureCacheDir')) {
    ensureCacheDir();
} elseif (!is_dir(CACHE_DIR)) {
    @mkdir(CACHE_DIR, 0755, true);
}

// 路由处理
$path = isset($_SERVER['REQUEST_URI'])
    ? rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/', '/')
    : '/';

// 健康检查端点：供负载均衡/监控探活，不返回任何敏感信息
// 必须排在所有外部网络请求之前，否则探活会被 GitHub 请求拖慢甚至超时
$isHealth = ($path === '/health' || $path === '/index.php/health'
    || (isset($_GET['health']) && (string)$_GET['health'] === '1' && !isset($_GET['owner'])));
if ($isHealth) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    // 站点在无出站网络时仍能用静态快照完整渲染，
    // 因此健康判定只覆盖真正影响可用性的条件。
    $checks = array(
        'cache_writable' => function_exists('ensureCacheDir') ? ensureCacheDir() : CACHE_AVAILABLE,
        'resources_loaded' => count($resources) > 0,
    );

    // 受限虚拟主机排查用：出站能力仅供诊断，不影响健康判定
    $probe = githubHttpGet('https://api.github.com/rate_limit', 8, true);
    $probeData = isset($probe['body']) ? githubDecodeJsonBody($probe['body']) : null;
    $cachedRoute = function_exists('file_cache_get') ? file_cache_get('github:route') : null;
    $lastError = function_exists('githubLastError') ? githubLastError() : null;
    $diagnostics = array(
        'curl_available' => function_exists('curl_init'),
        'http_transport' => githubHttpTransport(),
        'multi_curl' => supports_multi_curl(),
        'github_token' => getGitHubToken() !== '' ? 'configured' : 'missing',
        'github_route' => is_string($cachedRoute) && $cachedRoute !== '' ? $cachedRoute : 'unset',
        'api_status' => isset($probe['status']) ? (int)$probe['status'] : 0,
        'api_authenticated' => is_array($probeData) && isset($probeData['resources']['core']['limit'])
            ? ((int)$probeData['resources']['core']['limit'] >= 1000)
            : false,
        'last_error' => $lastError,
    );

    $healthy = !in_array(false, $checks, true);
    http_response_code($healthy ? 200 : 503);

    echo json_encode(array(
        'status' => $healthy ? 'ok' : 'degraded',
        'checks' => $checks,
        'diagnostics' => $diagnostics,
        'timestamp' => gmdate('c'),
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

// 资源详情页
if (isset($_GET['owner']) && isset($_GET['repo'])) {
    $owner = $_GET['owner'];
    $repo = $_GET['repo'];

    // 安全性校验：输入总长度不超过 MAX_INPUT_LENGTH
    if (strlen($owner) + strlen($repo) > MAX_INPUT_LENGTH) {
        http_response_code(400);
        exit('<h1>请求参数过长</h1>');
    }

    // 查找资源
    $resource = null;
    foreach ($resources as $r) {
        if (isset($r['owner']) && isset($r['repo'])
            && $r['owner'] === $owner
            && $r['repo'] === $repo) {
            $resource = $r;
            break;
        }
    }

    if (!$resource) {
        // 速率限制检查（针对自定义仓库查询）
        if (!checkRateLimit($_SERVER['REMOTE_ADDR'])) {
            http_response_code(429);
            exit('<h1>请求过于频繁</h1><p>请稍后再试</p>');
        }

        // 校验 owner/repo 格式，更严格的 GitHub 用户名/仓库名验证
        $ownerValid = preg_match('/^[A-Za-z0-9][A-Za-z0-9-]{0,38}$/', $owner);
        $repoValid  = preg_match('/^[A-Za-z0-9._-]{1,100}$/', $repo);
        if (!$ownerValid || !$repoValid) {
            http_response_code(404);
            exit('<h1>资源不存在</h1>');
        }
        $resource = array(
            'name'          => $owner . '/' . $repo,
            'owner'         => $owner,
            'repo'          => $repo,
            'description'   => '',
            'platforms'     => array(),
            'usePrerelease' => true,
            '_isCustom'     => true,
        );
    }

    $proxyUrls = isset($config['proxyUrls']) ? $config['proxyUrls'] : array('https://ghproxy.net/');
    $sourceType = isset($resource['sourceType']) ? $resource['sourceType'] : 'release';
    $repoDetail = getGitHubRepoDetailWithCache($owner, $repo, isset($resource['usePrerelease']) ? $resource['usePrerelease'] : false, $sourceType);
    $repoInfo = $repoDetail['repoInfo'];
    $releases = $repoDetail['releases'];

    if (is_array($releases) && !empty($releases) && empty($releases['error'])) {
        if (!(isset($releases[0]['prerelease']) && $releases[0]['prerelease'])) {
            $releases[0]['_isLatest'] = true;
        }
    }

    // 平台信息用于详情头部展示
    if (empty($resource['platforms'])) {
        $detailPlatforms = getResourcePlatformsBatch(array($resource));
        $resource['platforms'] = isset($detailPlatforms[0]) ? $detailPlatforms[0] : array();
    }

    // 片段模式：仅返回详情内容，供首页侧栏异步加载
    if (isset($_GET['fragment']) && $_GET['fragment'] === '1') {
        header('Content-Type: text/html; charset=UTF-8');
        include TEMPLATES_DIR . '/detail-fragment.php';
        exit;
    }

    include TEMPLATES_DIR . '/resource.php';
    exit;
}

// 首页

$marquee = isset($config['marquee']) && is_array($config['marquee']) ? $config['marquee'] : array();

// resources.json 中的静态快照是卡片渲染基线：受限虚拟主机缺少 cURL、
// 禁止出站网络或 GitHub API 限流时，Star、Fork 与 Release 时间仍可展示。
$resources = normalizeResourceCardData($resources);

// GitHub 数据属于增强信息，仅在主机确实具备出站能力时尝试刷新。
if (!empty($resources) && githubHttpTransport() !== 'none') {
    try {
        // 批量获取 Star/Fork/更新时间，并注入各资源卡片
        $repoList = array();
        foreach ($resources as $resource) {
            if (isset($resource['owner'], $resource['repo'])) {
                $repoList[] = array(
                    'owner' => $resource['owner'],
                    'repo' => $resource['repo'],
                );
            }
        }

        $stats = batchFetchRepoStats(
            $repoList,
            isset($config['proxyUrls']) ? $config['proxyUrls'] : array(),
            isset($config['githubToken']) ? $config['githubToken'] : null
        );

        foreach ($resources as $index => $resource) {
            $key = isset($resource['owner'], $resource['repo'])
                ? $resource['owner'] . '/' . $resource['repo']
                : '';
            if ($key === '' || !isset($stats[$key])) {
                continue;
            }
            $resources[$index]['stats'] = mergeRemoteRepoStats(
                $resources[$index]['stats'],
                $stats[$key]
            );
        }

        // 原有平台识别和更新时间查询；任一远程请求失败都由下方 catch 降级
        $platforms = getResourcePlatformsBatch($resources);
        $updatedAt = getResourceUpdatedAtBatch($resources);

        // 同一批 Release API 响应会命中缓存，单独保存最新 Release 发布时间。
        $releaseUpdatedAt = getResourceLatestReleaseAtBatch($resources);

        foreach ($resources as $index => $resource) {
            if (isset($platforms[$index]) && !empty($platforms[$index])) {
                $resources[$index]['platforms'] = $platforms[$index];
            }
            if (isset($updatedAt[$index]) && isValidIsoDate($updatedAt[$index])) {
                $resources[$index]['updatedAt'] = $updatedAt[$index];
            }
            if (isset($releaseUpdatedAt[$index]) && isValidIsoDate($releaseUpdatedAt[$index])) {
                $resources[$index]['releaseUpdatedAt'] = $releaseUpdatedAt[$index];
            }
        }
    } catch (Throwable $error) {
        error_log('首页 GitHub 增强信息加载失败，已降级为本地快照: ' . $error->getMessage());
    } catch (Exception $error) {
        // PHP 5.x/7.0 之前的扩展错误不实现 Throwable，保留兼容分支
        error_log('首页 GitHub 增强信息加载失败，已降级为本地快照: ' . $error->getMessage());
    }
}

// 推荐优先排序
usort($resources, function($a, $b) {
    $aRec = isset($a['recommended']) && $a['recommended'];
    $bRec = isset($b['recommended']) && $b['recommended'];
    if ($aRec && !$bRec) return -1;
    if (!$aRec && $bRec) return 1;
    return 0;
});

include TEMPLATES_DIR . '/home.php';
