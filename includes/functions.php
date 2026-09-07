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
 * UTF-8 字符串转小写；共享主机缺少 mbstring 时退回 ASCII 转换。
 */
function utf8Lower($value) {
    return function_exists('mb_strtolower')
        ? mb_strtolower($value, 'UTF-8')
        : strtolower($value);
}

/**
 * UTF-8 安全截断；共享主机缺少 mbstring 时按字节截断。
 */
function utf8Substring($value, $start, $length) {
    if (function_exists('mb_substr')) {
        return mb_substr($value, $start, $length, 'UTF-8');
    }

    // 按字节截断可能切断多字节字符，丢弃尾部残缺字节避免乱码。
    $sliced = substr($value, $start, $length);
    if (function_exists('iconv')) {
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $sliced);
        if ($clean !== false) {
            return $clean;
        }
    }

    return preg_replace('/[\x80-\xFF]+$/', '', $sliced);
}

function normalizeGitHubTokenValue($value) {
    if (!is_string($value)) {
        return '';
    }
    $value = trim($value);
    if (strncmp($value, "\xEF\xBB\xBF", 3) === 0) {
        $value = trim(substr($value, 3));
    }
    if ($value === '' || preg_match('/^(填写|your-?api-?key|ghp_x+)/i', $value)) {
        return '';
    }
    return $value;
}

function readGitHubTokenFromJsonFile($file) {
    if (!is_file($file)) {
        return '';
    }
    if (!is_readable($file)) {
        error_log('GitHub Token 配置文件存在但 PHP 进程无法读取: ' . basename($file));
        return '';
    }

    $raw = @file_get_contents($file);
    if ($raw === false || $raw === '') {
        return '';
    }
    if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
        $raw = substr($raw, 3);
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $stripped = preg_replace('#/\*.*?\*/#s', '', $raw);
        $stripped = preg_replace('#^\s*//.*$#m', '', $stripped);
        $stripped = preg_replace('/,\s*([}\]])/', '$1', $stripped);
        $data = json_decode($stripped, true);
    }
    if (!is_array($data)) {
        error_log('GitHub Token 配置文件 JSON 无效: ' . basename($file));
        return '';
    }

    foreach (array('githubToken', 'github_token', 'GITHUB_TOKEN') as $key) {
        if (!empty($data[$key])) {
            $token = normalizeGitHubTokenValue($data[$key]);
            if ($token !== '') {
                return $token;
            }
        }
    }

    return '';
}

/**
 * 获取 GitHub Token
 * 优先级: 环境变量 > config.local.json > resources.json
 */
function getGitHubToken() {
    static $token = null;
    if ($token !== null) {
        return $token;
    }

    $envToken = normalizeGitHubTokenValue((string)getenv('GITHUB_TOKEN'));
    if ($envToken !== '') {
        $token = $envToken;
        return $token;
    }

    $dataDir = defined('DATA_DIR') ? DATA_DIR : (__DIR__ . '/../data');
    $token = readGitHubTokenFromJsonFile($dataDir . '/config.local.json');
    if ($token !== '') {
        return $token;
    }

    $token = readGitHubTokenFromJsonFile($dataDir . '/resources.json');
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
        'Accept: application/vnd.github+json',
        'User-Agent: GitHub-Accel-Downloader/1.13',
        'X-GitHub-Api-Version: 2022-11-28',
    );

    $token = $includeToken ? getGitHubToken() : '';
    if ($includeToken && $token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
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
/**
 * 查找系统 CA bundle 路径（兼容不同发行版）
 */
function getSystemCaBundlePath() {
    static $path = null;
    if ($path !== null) {
        return $path;
    }
    $candidates = array(
        '/etc/ssl/certs/ca-certificates.crt',
        '/etc/pki/tls/certs/ca-bundle.crt',
        '/etc/ssl/ca-bundle.pem',
        '/etc/ssl/cert.pem',
        '/var/lib/ca-certificates/ca-bundle.pem',
        '/usr/local/share/certs/ca-root-nss.crt',
    );
    foreach ($candidates as $p) {
        if (is_file($p) && is_readable($p)) {
            $path = $p;
            return $path;
        }
    }
    // 未找到任何已知路径，返回空字符串（依赖系统默认）
    $path = '';
    return $path;
}

function _create_curl_handle($url, $timeout = 15, $includeToken = true) {
    // 部分共享虚拟主机未启用 cURL；此时返回 false，由上层降级为空数据。
    if (!function_exists('curl_init')) {
        return false;
    }

    $ch = curl_init();
    if ($ch === false) {
        return false;
    }
    $connectTimeout = (int)$timeout;
    if ($connectTimeout > 10) {
        $connectTimeout = 10;
    }
    if ($connectTimeout < 3) {
        $connectTimeout = 3;
    }

    // 经第三方加速代理访问时不附带 Token，避免凭证泄露
    if ($includeToken && strpos($url, 'https://api.github.com/') !== 0) {
        $includeToken = false;
    }

    $options = array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_USERAGENT => 'GitHub-Accel-Downloader/1.13',
        CURLOPT_HTTPHEADER => getGitHubApiHeaders($includeToken),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    );
    if (defined('CURL_IPRESOLVE_V4') && strpos($url, 'https://api.github.com/') === 0) {
        $options[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
    }
    // 显式指定 CA bundle，避免某些发行版默认路径不存在导致 SSL 握手失败
    $caBundle = getSystemCaBundlePath();
    if ($caBundle !== '' && function_exists('curl_setopt_array')) {
        $options[CURLOPT_CAINFO] = $caBundle;
    }
    curl_setopt_array($ch, $options);
    return $ch;
}

/**
 * 探测当前主机可用的 HTTP 出站通道
 *
 * 受限虚拟主机常见三种情况：启用 cURL、仅开放 allow_url_fopen、
 * 完全禁止出站请求。返回 'curl'、'stream' 或 'none'。
 */
function githubHttpTransport() {
    static $transport = null;

    if ($transport !== null) {
        return $transport;
    }

    if (function_exists('curl_init') && function_exists('curl_exec')) {
        $transport = 'curl';
    } elseif (function_exists('file_get_contents')
        && filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
        $transport = 'stream';
    } else {
        $transport = 'none';
    }

    return $transport;
}

function isGithubApiUrl($url) {
    return is_string($url) && strpos($url, 'https://api.github.com/') === 0;
}

function githubProxiedUrl($proxyBase, $url) {
    return rtrim($proxyBase, '/') . '/' . $url;
}

function getGithubApiProxyBases() {
    static $bases = null;
    if ($bases !== null) {
        return $bases;
    }

    if (!empty($GLOBALS['_legado_proxy_urls']) && is_array($GLOBALS['_legado_proxy_urls'])) {
        $bases = array_values($GLOBALS['_legado_proxy_urls']);
        return $bases;
    }

    $urls = array();
    $file = (defined('DATA_DIR') ? DATA_DIR : __DIR__ . '/../data') . '/resources.json';
    if (is_file($file)) {
        $data = @json_decode(@file_get_contents($file), true);
        if (is_array($data) && isset($data['proxyUrls'])) {
            $urls = $data['proxyUrls'];
        }
    }

    $defaults = array(
        'https://gproxy.mlds.dpdns.org/',
        'https://ghproxy.net/',
        'https://ghproxy.monkeyray.net/',
    );

    if (function_exists('sanitizeProxyUrls')) {
        $bases = array_values(array_unique(array_merge(sanitizeProxyUrls($urls), $defaults)));
    } else {
        $bases = $defaults;
    }

    return $bases;
}

function githubRememberRoute($route) {
    $GLOBALS['_github_route'] = $route;
    if (function_exists('file_cache_set')) {
        file_cache_set('github:route', $route, 600);
    }
}

function githubCurrentRoute() {
    return isset($GLOBALS['_github_route']) ? $GLOBALS['_github_route'] : '';
}

/**
 * 探测直连 GitHub API。有 Token 时必须带 Token 探测：
 * 数据中心 IP 对未认证请求常返回 403，若据此改走代理会丢掉 Token。
 */
function githubEnsureRoute() {
    if (githubCurrentRoute() !== '') {
        return;
    }

    if (function_exists('file_cache_get')) {
        $cached = file_cache_get('github:route');
        if ($cached === 'proxy' || $cached === 'direct') {
            $GLOBALS['_github_route'] = $cached;
            return;
        }
    }

    $hasToken = getGitHubToken() !== '';
    $probe = githubHttpGetRaw('https://api.github.com/rate_limit', 8, $hasToken);
    if (githubHttpResponseOk($probe) && githubDecodeJsonBody($probe['body'])) {
        githubRememberRoute('direct');
        return;
    }

    githubRememberRoute('direct-failed');
}

function githubMaybeRewriteUrl($url) {
    return $url;
}

function githubHttpResponseOk($result) {
    return is_array($result)
        && isset($result['status'], $result['body'])
        && (int)$result['status'] === 200
        && $result['body'] !== null
        && $result['body'] !== '';
}

function githubRememberLastError($status, $url, $detail) {
    $host = parse_url($url, PHP_URL_HOST);
    $path = parse_url($url, PHP_URL_PATH);
    $GLOBALS['_github_last_error'] = array(
        'status' => (int)$status,
        'host' => is_string($host) ? $host : '',
        'path' => is_string($path) ? $path : '',
        'detail' => is_string($detail) ? substr($detail, 0, 180) : '',
    );
}

function githubLastError() {
    return isset($GLOBALS['_github_last_error']) && is_array($GLOBALS['_github_last_error'])
        ? $GLOBALS['_github_last_error']
        : null;
}

function githubDecodeJsonBody($body) {
    if (!is_string($body) || $body === '') {
        return null;
    }
    if (strncmp($body, "\x1f\x8b", 2) === 0 && function_exists('gzdecode')) {
        $decoded = @gzdecode($body);
        if (is_string($decoded) && $decoded !== '') {
            $body = $decoded;
        }
    }
    $data = json_decode($body, true);
    return is_array($data) ? $data : null;
}

function githubFormatFetchError($fallback) {
    $err = githubLastError();
    $hasToken = getGitHubToken() !== '';
    $parts = array($fallback);
    if (is_array($err) && !empty($err['status'])) {
        $parts[] = 'HTTP ' . (int)$err['status'];
    } elseif (is_array($err) && !empty($err['detail'])) {
        $parts[] = $err['detail'];
    }
    $parts[] = $hasToken ? 'token=on' : 'token=off';
    return implode(' · ', $parts);
}

/**
 * 底层 GET，不走代理回退。
 *
 * @return array{status:int,body:string|null}
 */
function githubHttpGetRaw($url, $timeout = 15, $includeToken = true) {
    $transport = githubHttpTransport();

    if ($transport === 'curl') {
        $ch = _create_curl_handle($url, $timeout, $includeToken);
        if ($ch === false) {
            githubRememberLastError(0, $url, 'curl_init failed');
            return array('status' => 0, 'body' => null);
        }

        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false || $status === 0) {
            githubRememberLastError($status, $url, $err !== '' ? $err : 'empty curl response');
            return array('status' => $status, 'body' => null);
        }

        if ($status !== 200) {
            githubRememberLastError($status, $url, $err);
        }

        return array('status' => $status, 'body' => $body);
    }

    if ($transport === 'stream') {
        if ($includeToken && !isGithubApiUrl($url)) {
            $includeToken = false;
        }
        $context = stream_context_create(array(
            'http' => array(
                'method' => 'GET',
                'timeout' => $timeout,
                'header' => implode("\r\n", getGitHubApiHeaders($includeToken)),
                // 读取 4xx/5xx 响应体，便于按状态码降级而不是抛出警告
                'ignore_errors' => true,
            ),
            'ssl' => array(
                'verify_peer' => true,
                'verify_peer_name' => true,
                'cafile'  => getSystemCaBundlePath(),
            ),
        ));

        $body = @file_get_contents($url, false, $context);
        $status = 0;

        // file_get_contents 会在本作用域写入 $http_response_header
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches)) {
                    $status = (int)$matches[1];
                }
            }
        }

        if ($body === false || $status === 0) {
            githubRememberLastError($status, $url, 'stream request failed');
            return array('status' => $status, 'body' => null);
        }
        if ($status !== 200) {
            githubRememberLastError($status, $url, 'stream non-200');
        }

        return array('status' => $status, 'body' => $body);
    }

    return array('status' => 0, 'body' => null);
}

/**
 * 统一的 GitHub GET 请求
 *
 * 国内 VPS 直连 api.github.com 常被 403/超时拦截时，自动改走加速代理。
 *
 * @return array{status:int,body:string|null}
 */
function githubHttpGet($url, $timeout = 15, $includeToken = true) {
    $originalUrl = $url;
    $useToken = $includeToken && isGithubApiUrl($originalUrl);
    $result = githubHttpGetRaw($originalUrl, $timeout, $useToken);

    if ($result['status'] === 401 && $useToken) {
        $result = githubHttpGetRaw($originalUrl, $timeout, false);
        $useToken = false;
    }

    if (githubHttpResponseOk($result) && githubDecodeJsonBody($result['body'])) {
        githubRememberRoute('direct');
        return $result;
    }

    if (!isGithubApiUrl($originalUrl)) {
        return $result;
    }

    $bases = getGithubApiProxyBases();
    foreach ($bases as $base) {
        $proxied = githubProxiedUrl($base, $originalUrl);
        $retry = githubHttpGetRaw($proxied, $timeout, false);
        if (githubHttpResponseOk($retry) && githubDecodeJsonBody($retry['body'])) {
            githubRememberRoute('proxy');
            return $retry;
        }
    }

    return $result;
}

/**
 * 校验 ISO 时间字符串是否可用于展示
 */
function isValidIsoDate($value) {
    if (!is_string($value) || trim($value) === '') {
        return false;
    }

    $timestamp = strtotime($value);

    return $timestamp !== false && $timestamp > 0;
}

/**
 * 归一化首页卡片展示数据
 *
 * 把 resources.json 中的静态快照作为渲染基线：缺少 cURL、禁止出站
 * 网络或 GitHub API 限流时，Star、Fork 与最新 Release 时间依然可见。
 */
function normalizeResourceCardData($resources) {
    if (!is_array($resources)) {
        return array();
    }

    foreach ($resources as $index => $resource) {
        if (!is_array($resource)) {
            continue;
        }

        $stats = isset($resource['stats']) && is_array($resource['stats'])
            ? $resource['stats']
            : array();

        $stats['stars'] = isset($stats['stars']) && is_numeric($stats['stars'])
            ? (int)$stats['stars']
            : null;
        $stats['forks'] = isset($stats['forks']) && is_numeric($stats['forks'])
            ? (int)$stats['forks']
            : null;
        $stats['updated_at'] = isset($stats['updated_at']) && isValidIsoDate($stats['updated_at'])
            ? $stats['updated_at']
            : null;
        // 仅在仓库活跃度数据可信时保留标记，避免离线快照误判
        $stats['stale'] = $stats['updated_at'] !== null && !empty($stats['stale']);

        $resources[$index]['stats'] = $stats;

        if (!isset($resource['releaseUpdatedAt']) || !isValidIsoDate($resource['releaseUpdatedAt'])) {
            unset($resources[$index]['releaseUpdatedAt']);
        }

        if (!isset($resource['platforms']) || !is_array($resource['platforms'])) {
            $resources[$index]['platforms'] = array();
        }
    }

    return $resources;
}

/**
 * 用远程统计覆盖快照，仅接受完整有效的数值
 */
function mergeRemoteRepoStats($current, $remote) {
    $merged = is_array($current) ? $current : array();

    if (!is_array($remote)
        || !isset($remote['stars'], $remote['forks'])
        || !is_numeric($remote['stars']) || !is_numeric($remote['forks'])) {
        return $merged;
    }

    $merged['stars'] = (int)$remote['stars'];
    $merged['forks'] = (int)$remote['forks'];

    if (isset($remote['updated_at']) && isValidIsoDate($remote['updated_at'])) {
        $merged['updated_at'] = $remote['updated_at'];
        $merged['stale'] = !empty($remote['stale']);
    }

    return $merged;
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

    $result = githubHttpGet($url, 15, true);
    $response = $result['body'];
    $httpCode = $result['status'];

    $hasToken = getGitHubToken() !== '';

    if ($httpCode === 401 && $hasToken) {
        // token 无效，降级为未认证请求
        $result = githubHttpGet($url, 15, false);
        $response = $result['body'];
        $httpCode = $result['status'];
    }

    if ($httpCode === 403 && $hasToken) {
        // IP 被封或 token scope 不足，降级为未认证请求
        $result = githubHttpGet($url, 15, false);
        $response = $result['body'];
        $httpCode = $result['status'];
    }

    if ($httpCode !== 200 || !$response) {
        return null;
    }

    $data = githubDecodeJsonBody($response);
    if (!is_array($data)) {
        githubRememberLastError($httpCode, $url, 'json_decode failed');
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

    // 先排序，保证截取到的是最新的 3 条
    usort($data, function($a, $b) {
        $aTime = isset($a['published_at']) ? strtotime($a['published_at']) : 0;
        $bTime = isset($b['published_at']) ? strtotime($b['published_at']) : 0;
        return $bTime - $aTime;
    });

    // 仅转换需要的前 3 条，避免对全量数据做映射
    $result = array();
    foreach ($data as $r) {
        if (count($result) >= 3) {
            break;
        }

        $assets = array();
        if (isset($r['assets']) && is_array($r['assets'])) {
            foreach ($r['assets'] as $a) {
                $assets[] = array(
                    'name' => isset($a['name']) ? $a['name'] : '',
                    'size' => isset($a['size']) ? $a['size'] : 0,
                    'browser_download_url' => isset($a['browser_download_url']) ? $a['browser_download_url'] : '',
                );
            }
        }

        $result[] = array(
            'id' => isset($r['id']) ? $r['id'] : '',
            'tag_name' => isset($r['tag_name']) ? $r['tag_name'] : '',
            'name' => isset($r['name']) ? $r['name'] : '',
            'prerelease' => isset($r['prerelease']) ? $r['prerelease'] : false,
            'published_at' => isset($r['published_at']) ? $r['published_at'] : '',
            'body' => isset($r['body']) ? $r['body'] : '',
            'assets' => $assets,
        );
    }

    return $result;
}

/**
 * 精简 tag 数据（tag 没有 release 的完整信息）
 */
function normalizeGitHubTags($data) {
    if (!is_array($data)) {
        return array();
    }

    usort($data, function($a, $b) {
        $aDate = isset($a['commit']['committer']['date']) ? strtotime($a['commit']['committer']['date']) : 0;
        $bDate = isset($b['commit']['committer']['date']) ? strtotime($b['commit']['committer']['date']) : 0;
        return $bDate - $aDate;
    });

    // 仅转换需要的前 3 条
    $result = array();
    foreach ($data as $t) {
        if (count($result) >= 3) {
            break;
        }

        $name = isset($t['name']) ? $t['name'] : '';
        $commitDate = isset($t['commit']['committer']['date']) ? $t['commit']['committer']['date'] : '';

        $result[] = array(
            'id' => isset($t['commit']['sha']) ? $t['commit']['sha'] : $name,
            'tag_name' => $name,
            'name' => $name,
            'prerelease' => false,
            'published_at' => $commitDate,
            'body' => '',
            'assets' => array(),
            'zipball_url' => isset($t['zipball_url']) ? $t['zipball_url'] : '',
            'tarball_url' => isset($t['tarball_url']) ? $t['tarball_url'] : '',
        );
    }

    return $result;
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
        $result = array('error' => 'api', 'message' => githubFormatFetchError('获取 release 失败'));
        return $result;
    }

    $result = normalizeGitHubReleases($data, $includePrerelease);

    file_cache_set($cacheKey, $result, RELEASES_CACHE_TTL);
    return $result;
}

/**
 * 带缓存的 GitHub Tags API 调用
 */
function getGitHubTagsWithCache($owner, $repo) {
    $cacheKey = "tags:{$owner}/{$repo}";

    $cached = file_cache_get($cacheKey);
    if ($cached !== null) {
        return $cached;
    }

    $data = _github_api_request("https://api.github.com/repos/{$owner}/{$repo}/tags?per_page=5");

    if ($data === null) {
        $result = array('error' => 'api', 'message' => githubFormatFetchError('获取 tag 失败'));
        return $result;
    }

    $result = normalizeGitHubTags($data);

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
function getGitHubRepoDetailWithCache($owner, $repo, $includePrerelease = false, $sourceType = 'release') {
    $repoCacheKey = "repo:{$owner}/{$repo}";
    $releasesCacheKey = ($sourceType === 'tag' ? "tags:" : "releases:") . "{$owner}/{$repo}:" . ($includePrerelease ? '1' : '0');

    $repoInfo = file_cache_get($repoCacheKey);

    $releases = file_cache_get($releasesCacheKey);

    $urls = array();
    if ($repoInfo === null) {
        $urls['repo'] = "https://api.github.com/repos/{$owner}/{$repo}";
    }
    if ($releases === null) {
        if ($sourceType === 'tag') {
            $urls['tags'] = "https://api.github.com/repos/{$owner}/{$repo}/tags?per_page=5";
        } else {
            $urls['releases'] = "https://api.github.com/repos/{$owner}/{$repo}/releases?per_page=5";
        }
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
            if ($sourceType === 'tag') {
                $data = isset($responses['tags']) ? $responses['tags'] : null;
                if (is_array($data)) {
                    $releases = normalizeGitHubTags($data);
                    file_cache_set($releasesCacheKey, $releases, RELEASES_CACHE_TTL);
                } else {
                    $releases = array('error' => 'api', 'message' => githubFormatFetchError('获取 tag 失败'));
                }
            } else {
                $data = isset($responses['releases']) ? $responses['releases'] : null;
                if (is_array($data)) {
                    $releases = normalizeGitHubReleases($data, $includePrerelease);
                    file_cache_set($releasesCacheKey, $releases, RELEASES_CACHE_TTL);
                } else {
                    $releases = array('error' => 'api', 'message' => githubFormatFetchError('获取 release 失败'));
                }
            }
        }
    }

    return array(
        'repoInfo' => $repoInfo,
        'releases' => $releases === null ? array('error' => 'api', 'message' => githubFormatFetchError('获取版本失败')) : $releases,
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
    try {
        $dt = new DateTime($dateString);
        $dt->setTimezone(new DateTimeZone('Asia/Shanghai'));
        return $dt->format('Y-m-d H:i');
    } catch (Exception $e) {
        $ts = strtotime($dateString);
        return $ts ? date('Y-m-d H:i', $ts) : $dateString;
    }
}

/**
 * 随机选择代理
 * 多进程环境（php-fpm）下静态计数器各进程独立，会造成分配倾斜，
 * 因此改用随机选择保证整体分布均匀。
 */
function selectRandomProxy($urls) {
    $urls = array_values($urls);
    $cnt = count($urls);
    if ($cnt === 0) {
        return 'https://ghproxy.net/';
    }
    if ($cnt === 1) {
        return rtrim($urls[0], '/') . '/';
    }

    try {
        $idx = random_int(0, $cnt - 1);
    } catch (Exception $e) {
        $idx = mt_rand(0, $cnt - 1);
    }

    return rtrim($urls[$idx], '/') . '/';
}

/**
 * 构建加速链接
 */
function buildAcceleratedUrlOptimized($proxyConfig, $url) {
    $urls = is_array($proxyConfig) ? $proxyConfig : array($proxyConfig ?: 'https://ghproxy.net/');
    return selectRandomProxy($urls) . ltrim($url, '/');
}

/**
 * 零依赖最小 Markdown 子集渲染（安全优先）
 *
 * 策略：先完全转义，再选择性反转义白名单语法，确保用户内容不能注入 HTML/JS。
 * 支持：标题(#)、粗体(**)、代码(`、```)、链接[]()、无序列表(-)、有序列表(1.)
 * 不支持：图片、原始 HTML、脚本标签
 *
 * @param string $text 原始 Markdown 文本
 * @return string 安全的 HTML 片段
 */
function renderMarkdownSubset($text) {
    if ($text === '') {
        return '';
    }

    // 1. 先完全转义所有 HTML 实体，阻断任何注入可能
    $safe = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // 2. 按行处理（保留原始换行结构）
    $lines = explode("\n", $safe);
    $output = [];
    $inCodeBlock = false;
    $codeBuffer = [];
    $inList = false;
    $listType = ''; // 'ul' or 'ol'

    foreach ($lines as $line) {
        $trimmed = trim($line);

        // 代码块边界检测
        if (preg_match('/^```/', $trimmed)) {
            if ($inCodeBlock) {
                // 结束代码块
                $output[] = '<pre><code>' . implode("\n", $codeBuffer) . '</code></pre>';
                $codeBuffer = [];
                $inCodeBlock = false;
            } else {
                // 开始代码块（关闭列表如果打开）
                if ($inList) {
                    $output[] = '</' . $listType . '>';
                    $inList = false;
                }
                $inCodeBlock = true;
            }
            continue;
        }

        // 代码块内：保持原样（已转义）
        if ($inCodeBlock) {
            $codeBuffer[] = $line;
            continue;
        }

        // 空行：关闭列表，保留空行
        if ($trimmed === '') {
            if ($inList) {
                $output[] = '</' . $listType . '>';
                $inList = false;
            }
            $output[] = '';
            continue;
        }

        // 标题 (# - ######)
        if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $m)) {
            if ($inList) {
                $output[] = '</' . $listType . '>';
                $inList = false;
            }
            $level = strlen($m[1]);
            $content = processInline($m[2]);
            $output[] = '<h' . $level . '>' . $content . '</h' . $level . '>';
            continue;
        }

        // 无序列表 (- item 或 * item)
        if (preg_match('/^[-*]\s+(.+)$/', $trimmed, $m)) {
            if ($inList && $listType !== 'ul') {
                $output[] = '</' . $listType . '>';
                $inList = false;
            }
            if (!$inList) {
                $output[] = '<ul>';
                $inList = true;
                $listType = 'ul';
            }
            $output[] = '<li>' . processInline($m[1]) . '</li>';
            continue;
        }

        // 有序列表 (1. item)
        if (preg_match('/^\d+\.\s+(.+)$/', $trimmed, $m)) {
            if ($inList && $listType !== 'ol') {
                $output[] = '</' . $listType . '>';
                $inList = false;
            }
            if (!$inList) {
                $output[] = '<ol>';
                $inList = true;
                $listType = 'ol';
            }
            $output[] = '<li>' . processInline($m[1]) . '</li>';
            continue;
        }

        // 普通段落
        if ($inList) {
            $output[] = '</' . $listType . '>';
            $inList = false;
        }
        $output[] = '<p>' . processInline($trimmed) . '</p>';
    }

    // 收尾：关闭未闭合的代码块或列表
    if ($inCodeBlock && !empty($codeBuffer)) {
        $output[] = '<pre><code>' . implode("\n", $codeBuffer) . '</code></pre>';
    }
    if ($inList) {
        $output[] = '</' . $listType . '>';
    }

    return implode("\n", $output);
}

/**
 * 处理行内 Markdown 语法（已转义字符串上操作）
 *
 * @param string $text 已 htmlspecialchars 转义的文本
 * @return string 行内元素渲染后的 HTML
 */
function processInline($text) {
    // 先把安全链接替换为不可被 Markdown 正则命中的 token，避免 URL 中的
    // ** 或 ` 被后续规则改写成 HTML，造成 href 属性损坏。
    $links = array();
    $text = preg_replace_callback(
        '/\[([^\]]+)\]\(((?:[^()\s]|\([^()\s]*\))+)\)/',
        function($m) use (&$links) {
            $url = $m[2];
            // 还原转义以做协议判断（htmlspecialchars 会把 & 变成 &amp;）
            $probe = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            // 去掉可能用于绕过的空白与控制字符后再判断
            $probe = preg_replace('/[\s\x00-\x1F]+/', '', $probe);
            if (!preg_match('#^(https?://|mailto:)#i', $probe)) {
                // 协议不在白名单：降级为纯文本 [text]，不生成链接
                return '[' . $m[1] . ']';
            }

            $token = '@@LEGADO_LINK_' . count($links) . '@@';
            $links[$token] = '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>';
            return $token;
        },
        $text
    );

    // 粗体 **text**
    $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);

    // 行内代码 `code`
    $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);

    return empty($links) ? $text : strtr($text, $links);
}
