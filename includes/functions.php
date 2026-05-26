<?php
/**
 * 公共函数库 - UI保持不变的优化版
 */

// 内存缓存存储
$GLOBALS['_cache'] = [];
$GLOBALS['_cache_ttl'] = [];

/**
 * 获取缓存
 */
function _cache_get(string $key) {
    if (!isset($GLOBALS['_cache'][$key])) {
        return null;
    }
    if (isset($GLOBALS['_cache_ttl'][$key]) && $GLOBALS['_cache_ttl'][$key] < time()) {
        unset($GLOBALS['_cache'][$key], $GLOBALS['_cache_ttl'][$key]);
        return null;
    }
    return $GLOBALS['_cache'][$key];
}

/**
 * 设置缓存
 */
function _cache_set(string $key, $value, int $ttl = 300): void {
    $GLOBALS['_cache'][$key] = $value;
    $GLOBALS['_cache_ttl'][$key] = time() + $ttl;
}

/**
 * 获取 GitHub Token（单例缓存）
 */
function getGitHubToken(): string {
    static $token = null;
    if ($token === null) {
        $config = _cache_get('config');
        if ($config === null) {
            $configFile = __DIR__ . '/../data/resources.json';
            $config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
            _cache_set('config', $config, 60);
        }
        $token = $config['githubToken'] ?? '';
    }
    return $token;
}

/**
 * 构建 GitHub API 请求头
 */
function getGitHubApiHeaders(): array {
    static $headers = null;
    if ($headers !== null) {
        return $headers;
    }
    
    $headers = [
        'Accept: application/vnd.github.v3+json',
        'User-Agent: GitHub-Accel-Downloader/1.7',
    ];
    
    $token = getGitHubToken();
    if ($token !== '') {
        $headers[] = 'Authorization: token ' . $token;
    }
    
    return $headers;
}

/**
 * 统一 GitHub API 请求（新增）
 */
function _github_api_request(string $url): ?array {
    $cacheKey = 'api:' . md5($url);
    $cached = _cache_get($cacheKey);
    if ($cached !== null) {
        return $cached;
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => getGitHubApiHeaders(),
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) {
        return null;
    }
    
    $data = json_decode($response, true);
    if (!is_array($data)) {
        return null;
    }
    
    _cache_set($cacheKey, $data, 60);
    return $data;
}

/**
 * 带缓存的 GitHub API 调用 - 优化版
 */
function getGitHubReleasesWithCache(string $owner, string $repo, bool $includePrerelease = false): array {
    $cacheKey = "releases:{$owner}/{$repo}:" . ($includePrerelease ? '1' : '0');
    
    $cached = _cache_get($cacheKey);
    if ($cached !== null) {
        return $cached;
    }
    
    $data = _github_api_request("https://api.github.com/repos/{$owner}/{$repo}/releases?per_page=5");
    
    if ($data === null) {
        $result = ['error' => 'api', 'message' => '获取 release 失败'];
        _cache_set($cacheKey, $result, 30);
        return $result;
    }
    
    // 过滤预发布版本
    if (!$includePrerelease) {
        $data = array_values(array_filter($data, fn($r) => empty($r['prerelease'])));
    }
    
    // 精简数据，只保留必要字段
    $result = array_slice(array_map(fn($r) => [
        'id' => $r['id'],
        'tag_name' => $r['tag_name'],
        'name' => $r['name'] ?? '',
        'prerelease' => $r['prerelease'] ?? false,
        'published_at' => $r['published_at'],
        'body' => $r['body'] ?? '',
        'assets' => array_map(fn($a) => [
            'name' => $a['name'],
            'size' => $a['size'],
            'browser_download_url' => $a['browser_download_url'],
        ], $r['assets'] ?? []),
    ], $data), 0, 3);
    
    _cache_set($cacheKey, $result, 60);
    return $result;
}

/**
 * 获取仓库信息 - 优化版
 */
function getGitHubRepoInfo(string $owner, string $repo): ?array {
    $cacheKey = "repo:{$owner}/{$repo}";
    
    $cached = _cache_get($cacheKey);
    if ($cached !== null) {
        return $cached;
    }
    
    $data = _github_api_request("https://api.github.com/repos/{$owner}/{$repo}");
    
    if ($data === null) {
        _cache_set($cacheKey, null, 60);
        return null;
    }
    
    $result = [
        'stargazers_count' => $data['stargazers_count'] ?? 0,
        'forks_count' => $data['forks_count'] ?? 0,
    ];
    
    _cache_set($cacheKey, $result, 120);
    return $result;
}

/**
 * 平台检测 - 优化版（使用静态映射）
 */
function detectPlatforms(string $filename): array {
    static $patterns = [
        'Android' => ['.apk', 'arm64', 'armeabi', 'android'],
        'iOS' => ['.ipa', 'ios'],
        'Windows' => ['win', '.exe', '.msi'],
        'HarmonyOS' => ['harmony', 'hms'],
        'macOS' => ['mac', '.dmg'],
        'Linux' => ['linux', '.deb', '.rpm'],
    ];
    
    $lower = strtolower($filename);
    $platforms = [];
    
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
 * 获取资源平台列表 - 优化版
 */
function getResourcePlatforms(string $owner, string $repo): array {
    $cacheKey = "platforms:{$owner}/{$repo}";
    
    $cached = _cache_get($cacheKey);
    if ($cached !== null) {
        return $cached;
    }
    
    $data = _github_api_request("https://api.github.com/repos/{$owner}/{$repo}/releases?per_page=3");
    $platforms = [];
    
    if ($data !== null) {
        foreach ($data as $release) {
            foreach ($release['assets'] ?? [] as $asset) {
                $platforms = array_merge($platforms, detectPlatforms($asset['name'] ?? ''));
            }
        }
    }
    
    // 回退检测
    if (empty($platforms)) {
        $lower = strtolower($repo);
        if (strpos($lower, 'legado') !== false || strpos($lower, 'reader') !== false) {
            $platforms[] = 'Android';
        }
        if (strpos($lower, 'ios') !== false) {
            $platforms[] = 'iOS';
        }
        if (strpos($lower, 'win') !== false) {
            $platforms[] = 'Windows';
        }
    }
    
    $result = array_values(array_unique($platforms));
    _cache_set($cacheKey, $result, 300);
    return $result;
}

/**
 * 格式化文件大小
 */
function formatFileSizeOptimized(int $bytes): string {
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    $i = floor(log($bytes, 1024));
    return round($bytes / (1 << (10 * $i)), $i < 2 ? 0 : 2) . ' ' . ['B', 'KB', 'MB', 'GB'][$i];
}

/**
 * HTML 转义
 */
function h(string $string): string {
    return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * 格式化日期
 */
function formatDate(string $dateString): string {
    $ts = strtotime($dateString);
    return $ts ? date('Y-m-d H:i', $ts) : $dateString;
}

/**
 * 轮询选择代理
 */
function selectRandomProxy(array $urls): string {
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
function buildAcceleratedUrlOptimized($proxyConfig, string $url): string {
    $urls = is_array($proxyConfig) ? $proxyConfig : [$proxyConfig ?: 'https://ghproxy.net/'];
    return selectRandomProxy($urls) . ltrim($url, '/');
}
