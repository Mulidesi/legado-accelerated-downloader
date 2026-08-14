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

// 区分开发和生产环境
if (getenv('APP_ENV') === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');

    // 仅在日志目录确实可写时接管 error_log；否则保留主机默认日志，
    // 避免共享主机上目录不可写导致启动错误彻底无处可查。
    $logDir = __DIR__ . '/data/cache';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    if (is_dir($logDir) && is_writable($logDir)) {
        ini_set('error_log', $logDir . '/php_errors.log');
    }
    unset($logDir);
}

date_default_timezone_set('Asia/Shanghai');

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

if (!empty($config['legacyTokenInResources'])) {
    error_log('安全警告: GitHub Token 存储在 resources.json 中，建议迁移到环境变量或 config.local.json');
}

// 检查资源列表
$resources = isset($config['resources']) ? $config['resources'] : array();

if (empty($resources)) {
    http_response_code(500);
    exit('<h1>配置错误</h1><p>resources.json 不存在或格式错误，请确保包含 resources 数组</p>');
}

// 确保缓存目录存在
if (!is_dir(CACHE_DIR)) {
    @mkdir(CACHE_DIR, 0755, true);
}

// 路由处理
$path = isset($_SERVER['REQUEST_URI'])
    ? rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/', '/')
    : '/';

// 健康检查端点：供负载均衡/监控探活，不返回任何敏感信息
// 必须排在所有外部网络请求之前，否则探活会被 GitHub 请求拖慢甚至超时
if ($path === '/health') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    $checks = array(
        'cache_writable' => CACHE_AVAILABLE,
        'resources_loaded' => count($resources) > 0,
        'curl_available' => function_exists('curl_init'),
    );

    $healthy = !in_array(false, $checks, true);
    http_response_code($healthy ? 200 : 503);

    echo json_encode(array(
        'status' => $healthy ? 'ok' : 'degraded',
        'checks' => $checks,
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

// GitHub 统计属于增强信息。共享主机缺少 cURL、禁止出站网络或 API
// 暂时不可用时，首页继续使用 resources.json 中的基础数据正常渲染。
if (!empty($resources) && function_exists('curl_init')) {
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
            if ($key !== '' && isset($stats[$key])) {
                $resources[$index]['stats'] = $stats[$key];
            }
        }

        // 原有平台识别和更新时间查询；任一远程请求失败都由下方 catch 降级
        $platforms = getResourcePlatformsBatch($resources);
        $updatedAt = getResourceUpdatedAtBatch($resources);

        foreach ($resources as $index => $resource) {
            if (isset($platforms[$index]) && !empty($platforms[$index])) {
                $resources[$index]['platforms'] = $platforms[$index];
            } elseif (!isset($resources[$index]['platforms'])) {
                $resources[$index]['platforms'] = array();
            }
            $resources[$index]['updatedAt'] = isset($updatedAt[$index]) ? $updatedAt[$index] : null;
        }
    } catch (Throwable $error) {
        error_log('首页 GitHub 增强信息加载失败，已降级为本地配置: ' . $error->getMessage());
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
