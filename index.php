<?php
/**
 * Legado 资源加速下载站点
 * v1.7.0 (UI保持不变，后端优化)
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/includes/functions.php';

define('DATA_DIR', __DIR__ . '/data');
define('TEMPLATES_DIR', __DIR__ . '/templates');
define('CONFIG_FILE', DATA_DIR . '/resources.json');

// 读取并缓存配置
$config = _cache_get('config');
if ($config === null) {
    $config = json_decode(file_get_contents(CONFIG_FILE), true);
    if (!$config) {
        http_response_code(500);
        exit('<h1>配置错误</h1><p>配置文件 resources.json 不存在或格式错误</p>');
    }
    _cache_set('config', $config, 60);
}

$path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');

// 资源详情页
if (isset($_GET['owner'], $_GET['repo'])) {
    $owner = $_GET['owner'];
    $repo = $_GET['repo'];
    
    // 查找资源
    $resource = null;
    foreach ($config['resources'] ?? [] as $r) {
        if (($r['owner'] ?? '') === $owner && ($r['repo'] ?? '') === $repo) {
            $resource = $r;
            break;
        }
    }
    
    if (!$resource) {
        http_response_code(404);
        exit('<h1>资源不存在</h1><p>未在配置中找到该资源</p>');
    }
    
    // 必需字段检查
    $required = ['name', 'owner', 'repo', 'description', 'usePrerelease'];
    foreach ($required as $field) {
        if (!isset($resource[$field])) {
            http_response_code(500);
            exit("<h1>配置错误</h1><p>缺少字段：{$field}</p>");
        }
    }
    
    $proxyUrls = $config['proxyUrls'] ?? ($config['proxyUrl'] ?? 'https://ghproxy.net/');
    $repoInfo = getGitHubRepoInfo($owner, $repo);
    $releases = getGitHubReleasesWithCache($owner, $repo, $resource['usePrerelease'] ?? false);
    
    if (is_array($releases) && !empty($releases) && !($releases[0]['prerelease'] ?? false)) {
        $releases[0]['_isLatest'] = true;
    }
    
    include TEMPLATES_DIR . '/resource.php';
    exit;
}

// 首页
$resources = [];
$platformCache = [];

foreach ($config['resources'] ?? [] as $resource) {
    $key = $resource['owner'] . '/' . $resource['repo'];
    if (!isset($platformCache[$key])) {
        $platformCache[$key] = getResourcePlatforms($resource['owner'], $resource['repo']);
    }
    $resource['platforms'] = $platformCache[$key];
    $resources[] = $resource;
}

// 推荐优先排序
usort($resources, fn($a, $b) => ($b['recommended'] ?? false) <=> ($a['recommended'] ?? false));

include TEMPLATES_DIR . '/home.php';
