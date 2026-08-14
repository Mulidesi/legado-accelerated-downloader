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
    // 部分共享虚拟主机未启用 cURL；此时返回 false，由上层降级为空数据。
    if (!function_exists('curl_init')) {
        return false;
    }

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
        $result = array('error' => 'api', 'message' => '获取 release 失败');
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
        $result = array('error' => 'api', 'message' => '获取 tag 失败');
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
                    $releases = array('error' => 'api', 'message' => '获取 tag 失败');
                }
            } else {
                $data = isset($responses['releases']) ? $responses['releases'] : null;
                if (is_array($data)) {
                    $releases = normalizeGitHubReleases($data, $includePrerelease);
                    file_cache_set($releasesCacheKey, $releases, RELEASES_CACHE_TTL);
                } else {
                    $releases = array('error' => 'api', 'message' => '获取 release 失败');
                }
            }
        }
    }

    return array(
        'repoInfo' => $repoInfo,
        'releases' => $releases === null ? array('error' => 'api', 'message' => '获取版本失败') : $releases,
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
