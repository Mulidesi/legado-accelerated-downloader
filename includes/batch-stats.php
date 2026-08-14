<?php
/**
 * 批量预取仓库统计数据（Star/Fork/最后更新时间）
 * 
 * 策略：
 * - 使用 curl_multi 并发获取所有仓库数据（GitHub API repos 端点返回完整信息）
 * - 文件缓存 12 小时，避免频繁请求（未认证 60/hr 限额）
 * - 返回 owner/repo => [stars, forks, updated_at, stale] 的映射
 */

if (!defined('DATA_DIR')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

/**
 * 批量获取仓库统计信息
 * 
 * @param array $repos [[owner, repo], ...] 二维数组
 * @param array $proxyUrls 代理 URL 列表
 * @param string|null $token GitHub Token（可选）
 * @return array [owner/repo => [stars, forks, updated_at, stale], ...]
 */
function batchFetchRepoStats($repos, $proxyUrls, $token = null) {
    if (empty($repos)) {
        return array();
    }

    $cacheKey = 'repo_stats_' . md5(json_encode($repos));
    $cached = file_cache_get($cacheKey);
    if ($cached !== null) {
        return $cached;
    }

    // 构建 API 请求（每个仓库对应 /repos/:owner/:repo 端点）
    $urls = array();
    $repoKeys = array();
    foreach ($repos as $r) {
        if (!isset($r['owner'], $r['repo'])) {
            continue;
        }
        $owner = $r['owner'];
        $repo = $r['repo'];
        $key = $owner . '/' . $repo;
        $apiUrl = 'https://api.github.com/repos/' . urlencode($owner) . '/' . urlencode($repo);
        $urls[] = $apiUrl;
        $repoKeys[] = $key;
    }

    if (empty($urls)) {
        return array();
    }

    // curl_multi 并发请求
    $responses = curl_multi_fetch($urls, $token);

    // 解析响应
    $stats = array();
    $successCount = 0;
    foreach ($repoKeys as $idx => $key) {
        $body = isset($responses[$idx]) ? $responses[$idx] : null;
        $data = $body ? json_decode($body, true) : null;

        if (!is_array($data) || !isset($data['stargazers_count'])) {
            // API 失败（限额/网络错误）：填充 null，稍后判断是否缓存
            $stats[$key] = array(
                'stars' => null,
                'forks' => null,
                'updated_at' => null,
                'stale' => false,
            );
            continue;
        }

        $successCount++;
        $updatedAt = isset($data['updated_at']) ? $data['updated_at'] : null;
        $stale = false;
        if ($updatedAt !== null) {
            $updatedTime = strtotime($updatedAt);
            $stale = (time() - $updatedTime) > (30 * 86400); // 超过 30 天
        }

        $stats[$key] = array(
            'stars' => (int)$data['stargazers_count'],
            'forks' => (int)$data['forks_count'],
            'updated_at' => $updatedAt,
            'stale' => $stale,
        );
    }

    // 仅在至少一半请求成功时写入长效缓存（12 小时）
    // 全部失败（通常是 API 限额）则短效缓存（5 分钟），避免限额恢复后长时间无数据
    $ttl = ($successCount >= max(1, intval(count($repoKeys) / 2))) ? 43200 : 300;
    file_cache_set($cacheKey, $stats, $ttl);

    return $stats;
}

/**
 * curl_multi 并发抓取多个 URL（复用现有 includes/cache.php 逻辑）
 * 
 * @param array $urls URL 列表
 * @param string|null $token GitHub Token
 * @return array 响应体数组（按 URL 顺序）
 */
function curl_multi_fetch($urls, $token = null) {
    if (!function_exists('curl_multi_init') || !function_exists('curl_init')) {
        return array_fill(0, count($urls), null);
    }

    $mh = curl_multi_init();
    $handles = array();
    $userAgent = 'Legado-Resource-Accelerator/1.0';

    foreach ($urls as $idx => $url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $headers = array('Accept: application/vnd.github.v3+json');
        if ($token !== null && trim($token) !== '') {
            $headers[] = 'Authorization: token ' . $token;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        curl_multi_add_handle($mh, $ch);
        $handles[$idx] = $ch;
    }

    // 执行并发请求（带超时保护）
    $startTime = time();
    $timeout = 15; // 全局超时 15 秒
    do {
        $status = curl_multi_exec($mh, $active);
        if ($active) {
            curl_multi_select($mh, 0.1);
        }
        // 墙钟超时保护
        if ((time() - $startTime) > $timeout) {
            break;
        }
    } while ($active && $status === CURLM_OK);

    // 收集响应
    $responses = array();
    foreach ($handles as $idx => $ch) {
        $responses[$idx] = curl_multi_getcontent($ch);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);

    return $responses;
}
