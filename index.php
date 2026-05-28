<?php
/**
 * Legado 资源加速下载站点
 * v1.7.3 - 安全增强版
 * 
 * 安全改进:
 * - GitHub Token 从环境变量或本地配置读取
 * - resources.json 不再包含敏感信息
 * - 添加 .gitignore 防止敏感文件提交
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/cache.php';
require_once __DIR__ . '/includes/config.php';

define('DATA_DIR', __DIR__ . '/data');
define('TEMPLATES_DIR', __DIR__ . '/templates');
define('CACHE_DIR', DATA_DIR . '/cache');

// 安全警告：如果 GitHub Token 在 resources.json 中，提示用户迁移
$resourcesJson = @json_decode(file_get_contents(DATA_DIR . '/resources.json'), true);
if (is_array($resourcesJson) && isset($resourcesJson['githubToken']) && !empty($resourcesJson['githubToken'])) {
    // 记录警告但不阻止运行（兼容旧版本）
    error_log('安全警告: GitHub Token 存储在 resources.json 中，建议迁移到环境变量或 config.local.json');
}

// 加载安全配置
$config = loadSecureConfig();

// 检查资源列表
$resources = isset($config['resources']) ? $config['resources'] : array();

if (empty($resources)) {
    // 尝试直接从 resources.json 读取（兼容旧版本）
    $resourcesFile = DATA_DIR . '/resources.json';
    if (file_exists($resourcesFile)) {
        $resourcesData = @json_decode(file_get_contents($resourcesFile), true);
        if (is_array($resourcesData) && isset($resourcesData['resources']) && !empty($resourcesData['resources'])) {
            $resources = $resourcesData['resources'];
        }
    }
}

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

// 资源详情页
if (isset($_GET['owner']) && isset($_GET['repo'])) {
    $owner = $_GET['owner'];
    $repo = $_GET['repo'];
    
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
        http_response_code(404);
        exit('<h1>资源不存在</h1>');
    }
    
    $proxyUrls = isset($config['proxyUrls']) ? $config['proxyUrls'] : array('https://ghproxy.net/');
    $repoInfo = getGitHubRepoInfo($owner, $repo);
    $releases = getGitHubReleasesWithCache($owner, $repo, isset($resource['usePrerelease']) ? $resource['usePrerelease'] : false);
    
    if (is_array($releases) && !empty($releases) && empty($releases['error'])) {
        if (!(isset($releases[0]['prerelease']) && $releases[0]['prerelease'])) {
            $releases[0]['_isLatest'] = true;
        }
    }
    
    include TEMPLATES_DIR . '/resource.php';
    exit;
}

// 首页

// 批量并发获取平台信息
if (!empty($resources)) {
    $platforms = getResourcePlatformsBatch($resources);
    
    foreach ($resources as $index => $resource) {
        $resources[$index]['platforms'] = isset($platforms[$index]) ? $platforms[$index] : array();
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
