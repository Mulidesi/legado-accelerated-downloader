<?php
/**
 * 公共函数库 - 兼容性修复版
 */

if (!defined('DATA_DIR')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

if (!defined('API_CACHE_TTL')) {
    define('API_CACHE_TTL', 900);
}
if (!defined('RELEASES_CACHE_TTL')) {
    define('RELEASES_CACHE_TTL', 900);
}
if (!defined('REPO_INFO_CACHE_TTL')) {
    define('REPO_INFO_CACHE_TTL', 3600);
}
if (!defined('PLATFORMS_CACHE_TTL')) {
    define('PLATFORMS_CACHE_TTL', 21600);
}
if (!defined('RESOURCE_UPDATE_CACHE_TTL')) {
    define('RESOURCE_UPDATE_CACHE_TTL', 3600);
}

/**
 * 获取 GitHub Token - 安全增强版
 * 优先级: 环境变量 > 本地配置 > resources.json
 */
function getGitHubToken() {
    static $token = null;
    if ($token === null) {
        // 1. 优先从环境变量读取（最安全）
        $envToken = getenv('GITHUB_TOKEN');
        if (!empty($envToken)) {
            $token = $envToken;
            return $token;
        }
        
        // 2. 从本地配置文件读取（不会被提交到Git）
        $localConfigFile = __DIR__ . '/../data/config.local.json';
        if (file_exists($localConfigFile)) {
            $localConfig = @json_decode(file_get_contents($localConfigFile), true);
            if (is_array($localConfig) && isset($localConfig['githubToken']) && !empty($localConfig['githubToken'])) {
                $token = $localConfig['githubToken'];
                return $token;
            }
        }
        
        // 3. 兼容旧版本：从 resources.json 读取（不推荐，会提示警告）
        $configFile = __DIR__ . '/../data/resources.json';
        if (file_exists($configFile)) {
            $config = @json_decode(file_get_contents($configFile), true);
            if (is_array($config) && isset($config['githubToken'])) {
                $token = $config['githubToken'];
            }
        }
        
        // 如果都未找到，返回空字符串
        if ($token === null) {
            $token = '';
        }
    }
    return $token;
}

/**
 * 构建 GitHub API 请求头
 */
function getGitHubApiHeaders($includeToken = true) {
    static $headersWithToken = null;
    static $headersWithoutToken = null;

    if (!$includeToken && $headersWithoutToken !== null) {
        return $headersWithoutToken;
    }
    if ($includeToken && $headersWithToken !== null) {
        return $headersWithToken;
    }

    $headers = array(
        'Accept: application/vnd.github.v3+json',
        'User-Agent: GitHub-Accel-Downloader/1.7',
    );

    $token = $includeToken ? getGitHubToken() : '';
    if ($includeToken && $token !== '') {
        $headers[] = 'Authorization: token ' . $token;
    }

    if ($includeToken) {
        $headersWithToken = $headers;
    } else {
        $headersWithoutToken = $headers;
    }

    return $headers;
}

/**
 * 创建 cURL 句柄
 */
function _create_curl_handle($url, $timeout = 15, $includeToken = true) {
    $ch = curl_init();
    if ($ch === false) {
        return false;
    }
    curl_setopt_array($ch, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => getGitHubApiHeaders($includeToken),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ));
    return $ch;
}

/**
 * 统一 GitHub API 请求
 */
function _github_api_request($url) {
    $cacheKey = 'api:' . md5($url);
    $cached = file_cache_get($cacheKey);
    if ($cached !== null) {
        return $cached;
    }
    
    $ch = _create_curl_handle($url);
    if ($ch === false) {
        return null;
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 401 && getGitHubToken() !== '') {
        $ch = _create_curl_handle($url, 15, false);
        if ($ch === false) {
            return null;
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    }
    
    if ($httpCode !== 200 || !$response) {
        return null;
    }
    
    $data = json_decode($response, true);
    if (!is_array($data)) {
        return null;
    }
    
    file_cache_set($cacheKey, $data, API_CACHE_TTL);
    return $data;
}

/**
 * 精简 release 数据
 */
function normalizeGitHubReleases($data, $includePrerelease = false) {
    if (!is_array($data)) {
        return array();
    }

    if (!$includePrerelease) {
        $filtered = array();
        foreach ($data as $r) {
            if (empty($r['prerelease'])) {
                $filtered[] = $r;
            }
        }
        $data = $filtered;
    }

    return array_slice(array_map(function($r) {
        return array(
            'id' => $r['id'],
            'tag_name' => $r['tag_name'],
            'name' => isset($r['name']) ? $r['name'] : '',
            'prerelease' => isset($r['prerelease']) ? $r['prerelease'] : false,
            'published_at' => $r['published_at'],
            'body' => isset($r['body']) ? $r['body'] : '',
            'assets' => array_map(function($a) {
                return array(
                    'name' => $a['name'],
                    'size' => $a['size'],
                    'browser_download_url' => $a['browser_download_url'],
                );
            }, isset($r['assets']) ? $r['assets'] : array()),
        );
    }, $data), 0, 3);
}

/**
 * 带缓存的 GitHub API 调用
 */
function getGitHubReleasesWithCache($owner, $repo, $includePrerelease = false) {
    $cacheKey = "releases:{$owner}/{$repo}:" . ($includePrerelease ? '1' : '0');

    $cached = file_cache_get($cacheKey);
    if ($cached !== null) {
        return $cached;
    }

    $data = _github_api_request("https://api.github.com/repos/{$owner}/{$repo}/releases?per_page=5");

    if ($data === null) {
        $result = array('error' => 'api', 'message' => '获取 release 失败');
        return $result;
    }

    $result = normalizeGitHubReleases($data, $includePrerelease);

    file_cache_set($cacheKey, $result, RELEASES_CACHE_TTL);
    return $result;
}

/**
 * 获取仓库信息
 */
function getGitHubRepoInfo($owner, $repo) {
    $cacheKey = "repo:{$owner}/{$repo}";
    
    $cached = file_cache_get($cacheKey);
    if ($cached !== null) {
        return $cached;
    }
    
    $data = _github_api_request("https://api.github.com/repos/{$owner}/{$repo}");
    
    if ($data === null) {
        return null;
    }
    
    $result = array(
        'stargazers_count' => isset($data['stargazers_count']) ? $data['stargazers_count'] : 0,
        'forks_count' => isset($data['forks_count']) ? $data['forks_count'] : 0,
    );
    
    file_cache_set($cacheKey, $result, REPO_INFO_CACHE_TTL);
    return $result;
}

/**
 * 并发获取仓库详情页数据
 */
function getGitHubRepoDetailWithCache($owner, $repo, $includePrerelease = false) {
    $repoCacheKey = "repo:{$owner}/{$repo}";
    $releasesCacheKey = "releases:{$owner}/{$repo}:" . ($includePrerelease ? '1' : '0');

    $repoInfo = file_cache_get($repoCacheKey);

    $releases = file_cache_get($releasesCacheKey);

    $urls = array();
    if ($repoInfo === null) {
        $urls['repo'] = "https://api.github.com/repos/{$owner}/{$repo}";
    }
    if ($releases === null) {
        $urls['releases'] = "https://api.github.com/repos/{$owner}/{$repo}/releases?per_page=5";
    }

    if (!empty($urls)) {
        $responses = github_api_multi_request($urls, 15, RELEASES_CACHE_TTL);

        if ($repoInfo === null) {
            $data = isset($responses['repo']) ? $responses['repo'] : null;
            if (is_array($data)) {
                $repoInfo = array(
                    'stargazers_count' => isset($data['stargazers_count']) ? $data['stargazers_count'] : 0,
                    'forks_count' => isset($data['forks_count']) ? $data['forks_count'] : 0,
                );
                file_cache_set($repoCacheKey, $repoInfo, REPO_INFO_CACHE_TTL);
            }
        }

        if ($releases === null) {
            $data = isset($responses['releases']) ? $responses['releases'] : null;
            if (is_array($data)) {
                $releases = normalizeGitHubReleases($data, $includePrerelease);
                file_cache_set($releasesCacheKey, $releases, RELEASES_CACHE_TTL);
            } else {
                $releases = array('error' => 'api', 'message' => '获取 release 失败');
            }
        }
    }

    return array(
        'repoInfo' => $repoInfo,
        'releases' => $releases === null ? array('error' => 'api', 'message' => '获取 release 失败') : $releases,
    );
}

/**
 * 平台检测
 */
function detectPlatforms($filename) {
    static $patterns = array(
        'Android' => array('.apk', 'arm64', 'armeabi', 'android'),
        'iOS' => array('.ipa', 'ios'),
        'Windows' => array('win', '.exe', '.msi'),
        'HarmonyOS' => array('harmony', 'hms'),
        'macOS' => array('mac', '.dmg'),
        'Linux' => array('linux', '.deb', '.rpm'),
    );
    
    $lower = strtolower($filename);
    $platforms = array();
    
    foreach ($patterns as $platform => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($lower, $keyword) !== false) {
                $platforms[] = $platform;
                break;
            }
        }
    }
    
    if (empty($platforms) && (strpos($lower, 'app') !== false || strpos($lower, 'legado') !== false)) {
        $platforms[] = 'Android';
    }
    
    return array_values(array_unique($platforms));
}

/**
 * 格式化文件大小
 */
function formatFileSizeOptimized($bytes) {
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    $i = floor(log($bytes, 1024));
    return round($bytes / (1 << (10 * $i)), $i < 2 ? 0 : 2) . ' ' . array('B', 'KB', 'MB', 'GB')[$i];
}

/**
 * HTML 转义
 */
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * 格式化日期
 */
function formatDate($dateString) {
    $ts = strtotime($dateString);
    return $ts ? date('Y-m-d H:i', $ts) : $dateString;
}

/**
 * 轮询选择代理
 */
function selectRandomProxy($urls) {
    static $idx = -1;
    $cnt = count($urls);
    if ($cnt === 0) {
        return 'https://ghproxy.net/';
    }
    $idx = ($idx + 1) % $cnt;
    return rtrim($urls[$idx], '/') . '/';
}

/**
 * 构建加速链接
 */
function buildAcceleratedUrlOptimized($proxyConfig, $url) {
    $urls = is_array($proxyConfig) ? $proxyConfig : array($proxyConfig ?: 'https://ghproxy.net/');
    return selectRandomProxy($urls) . ltrim($url, '/');
}
