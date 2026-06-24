<?php
/**
 * 文件缓存系统 - 兼容性修复版
 */

if (!defined('DATA_DIR')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

// 确保缓存目录存在
if (!is_dir(CACHE_DIR)) {
    @mkdir(CACHE_DIR, 0755, true);
}

/**
 * 获取文件缓存
 */
function file_cache_get($key) {
    $file = CACHE_DIR . '/' . md5($key) . '.json';
    
    if (!file_exists($file)) {
        return null;
    }
    
    $content = @file_get_contents($file);
    if ($content === false) {
        return null;
    }
    
    $data = json_decode($content, true);
    if (!$data || !is_array($data)) {
        return null;
    }
    
    if (!isset($data['expire']) || !isset($data['value'])) {
        return null;
    }
    
    if ($data['expire'] < time()) {
        @unlink($file);
        return null;
    }
    
    return $data['value'];
}

/**
 * 设置文件缓存
 */
function file_cache_set($key, $value, $ttl = 3600) {
    $file = CACHE_DIR . '/' . md5($key) . '.json';
    
    // 如果目录不可写，直接返回（降级处理）
    if (!is_dir(CACHE_DIR) || !is_writable(CACHE_DIR)) {
        return false;
    }
    
    $data = array(
        'expire' => time() + $ttl,
        'value' => $value,
    );
    
    @file_put_contents($file, json_encode($data), LOCK_EX);
    return true;
}

/**
 * 检查是否支持并发请求
 */
function supports_multi_curl() {
    return function_exists('curl_multi_init') && function_exists('curl_multi_exec');
}

/**
 * 并发 GitHub API 请求
 */
function github_api_multi_request($urls, $timeout = 15, $cacheTtl = API_CACHE_TTL) {
    if (empty($urls)) {
        return array();
    }
    
    // 检查缓存
    $results = array();
    $urls_to_fetch = array();
    
    foreach ($urls as $key => $url) {
        $cacheKey = 'api:' . md5($url);
        $cached = file_cache_get($cacheKey);
        if ($cached !== null) {
            $results[$key] = $cached;
        } else {
            $urls_to_fetch[$key] = $url;
        }
    }
    
    if (empty($urls_to_fetch)) {
        return $results;
    }
    
    // 如果不支持并发，降级为顺序请求
    if (!supports_multi_curl()) {
        foreach ($urls_to_fetch as $key => $url) {
            $results[$key] = github_api_single_request($url, $timeout, $cacheTtl);
        }
        return $results;
    }
    
    // 并发请求
    $mh = curl_multi_init();
    if ($mh === false) {
        // 并发失败，降级为顺序请求
        foreach ($urls_to_fetch as $key => $url) {
            $results[$key] = github_api_single_request($url, $timeout, $cacheTtl);
        }
        return $results;
    }
    
    $chs = array();
    
    foreach ($urls_to_fetch as $key => $url) {
        $ch = _create_curl_handle($url, $timeout);
        if ($ch === false) {
            continue;
        }
        curl_multi_add_handle($mh, $ch);
        $chs[$key] = $ch;
    }
    
    if (empty($chs)) {
        curl_multi_close($mh);
        return $results;
    }
    
    // 执行所有请求
    $running = null;
    do {
        do {
            $execResult = curl_multi_exec($mh, $running);
        } while ($execResult === CURLM_CALL_MULTI_PERFORM);

        if ($execResult !== CURLM_OK) {
            break;
        }

        if ($running > 0) {
            $selectResult = curl_multi_select($mh, 1.0);
            if ($selectResult === -1) {
                usleep(100000);
            }
        }
    } while ($running > 0);
    
    // 收集结果
    foreach ($chs as $key => $ch) {
        $response = curl_multi_getcontent($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (is_array($data)) {
                $results[$key] = $data;
                file_cache_set('api:' . md5($urls_to_fetch[$key]), $data, $cacheTtl);
            } else {
                $results[$key] = null;
            }
        } elseif ($httpCode === 401 && getGitHubToken() !== '') {
            $results[$key] = github_api_single_request($urls_to_fetch[$key], $timeout, $cacheTtl, true);
        } else {
            $results[$key] = null;
        }
    }
    
    curl_multi_close($mh);
    return $results;
}

/**
 * 单个 API 请求（降级用）
 */
function github_api_single_request($url, $timeout = 15, $cacheTtl = API_CACHE_TTL, $includeToken = true) {
    $cacheKey = 'api:' . md5($url);
    $cached = file_cache_get($cacheKey);
    if ($cached !== null) {
        return $cached;
    }
    
    $ch = _create_curl_handle($url, $timeout, $includeToken);
    if ($ch === false) {
        return null;
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 401 && $includeToken && getGitHubToken() !== '') {
        return github_api_single_request($url, $timeout, $cacheTtl, false);
    }
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if (is_array($data)) {
            file_cache_set($cacheKey, $data, $cacheTtl);
            return $data;
        }
    }
    
    return null;
}

/**
 * 批量获取资源平台信息
 */
function getResourcePlatformsBatch($resources) {
    if (empty($resources)) {
        return array();
    }
    
    // 构建请求列表，按 owner/repo 去重
    $urls = array();
    $resourceMap = array();
    $repoKeys = array();
    
    foreach ($resources as $index => $resource) {
        if (isset($resource['platforms']) && is_array($resource['platforms'])) {
            $resourceMap[$index] = array_values(array_unique($resource['platforms']));
            continue;
        }

        if (!isset($resource['owner']) || !isset($resource['repo'])) {
            $resourceMap[$index] = array();
            continue;
        }
        
        $key = $resource['owner'] . '/' . $resource['repo'];
        
        // 先检查缓存
        $cacheKey = "platforms:{$key}";
        $cached = file_cache_get($cacheKey);
        if ($cached !== null) {
            $resourceMap[$index] = $cached;
            continue;
        }
        
        if (!isset($repoKeys[$key])) {
            $repoKeys[$key] = array(
                'indices' => array(),
                'sourceType' => isset($resource['sourceType']) ? $resource['sourceType'] : 'release',
            );
        }
        $repoKeys[$key]['indices'][] = $index;
        $resourceMap[$index] = null;
    }
    
    // 按唯一 repo 构建请求列表
    foreach ($repoKeys as $key => $info) {
        if ($info['sourceType'] === 'tag') {
            $urls[$key] = "https://api.github.com/repos/{$key}/tags?per_page=3";
        } else {
            $urls[$key] = "https://api.github.com/repos/{$key}/releases?per_page=3";
        }
    }
    
    // 并发请求
    if (!empty($urls)) {
        $responses = github_api_multi_request($urls, 15, PLATFORMS_CACHE_TTL);
        
        foreach ($responses as $key => $data) {
            $info = $repoKeys[$key];
            $indices = $info['indices'];
            $platforms = array();
            
            if (is_array($data)) {
                foreach ($data as $item) {
                    if ($info['sourceType'] === 'tag') {
                        // Tag 没有 assets，只能从 repo 名检测平台
                        break;
                    }
                    $assets = isset($item['assets']) ? $item['assets'] : array();
                    foreach ($assets as $asset) {
                        $name = isset($asset['name']) ? $asset['name'] : '';
                        $platforms = array_merge($platforms, detectPlatforms($name));
                    }
                }
            }
            
            // 回退检测
            if (empty($platforms) && count($indices) > 0) {
                $firstResource = $resources[$indices[0]];
                if (isset($firstResource['repo'])) {
                    $lower = strtolower($firstResource['repo']);
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
            }
            
            $platforms = array_values(array_unique($platforms));
            
            // 应用到所有共享该 repo 的资源
            foreach ($indices as $idx) {
                $resourceMap[$idx] = $platforms;
            }
            
            file_cache_set("platforms:{$key}", $platforms, PLATFORMS_CACHE_TTL);
        }
    }
    
    return $resourceMap;
}

/**
 * 批量获取资源最近更新时间
 */
function getResourceUpdatedAtBatch($resources) {
    if (empty($resources)) {
        return array();
    }

    $urls = array();
    $resourceMap = array();
    $repoKeys = array();

    foreach ($resources as $index => $resource) {
        if (!empty($resource['updatedAt'])) {
            $resourceMap[$index] = $resource['updatedAt'];
            continue;
        }

        if (!isset($resource['owner']) || !isset($resource['repo'])) {
            $resourceMap[$index] = null;
            continue;
        }

        $key = $resource['owner'] . '/' . $resource['repo'];
        $includePrerelease = isset($resource['usePrerelease']) ? $resource['usePrerelease'] : false;
        $sourceType = isset($resource['sourceType']) ? $resource['sourceType'] : 'release';
        $cacheKey = "updated_at:{$key}:" . ($includePrerelease ? '1' : '0') . ":{$sourceType}";
        $cached = file_cache_get($cacheKey);
        if ($cached !== null) {
            $resourceMap[$index] = $cached;
            continue;
        }

        if (!isset($repoKeys[$key])) {
            $repoKeys[$key] = array(
                'indices' => array(),
                'includePrerelease' => $includePrerelease,
                'sourceType' => $sourceType,
            );
        }
        $repoKeys[$key]['indices'][] = $index;
        $resourceMap[$index] = null;
    }

    // 按唯一 repo 构建请求列表
    foreach ($repoKeys as $key => $info) {
        if ($info['sourceType'] === 'tag') {
            $urls[$key] = "https://api.github.com/repos/{$key}/tags?per_page=3";
        } else {
            $urls[$key] = "https://api.github.com/repos/{$key}/releases?per_page=3";
        }
    }

    if (!empty($urls)) {
        $responses = github_api_multi_request($urls, 15, RESOURCE_UPDATE_CACHE_TTL);

        foreach ($responses as $key => $data) {
            $info = $repoKeys[$key];
            $indices = $info['indices'];
            $includePrerelease = $info['includePrerelease'];
            $sourceType = $info['sourceType'];
            $updatedAt = null;

            if (is_array($data)) {
                if ($sourceType === 'tag') {
                    // Tag: 取第一个 tag 的 commit date
                    if (isset($data[0]['commit']['committer']['date'])) {
                        $updatedAt = $data[0]['commit']['committer']['date'];
                    }
                } else {
                    foreach ($data as $r) {
                        if (!$includePrerelease && !empty($r['prerelease'])) {
                            continue;
                        }
                        $updatedAt = isset($r['published_at']) ? $r['published_at'] : null;
                        break;
                    }
                }
            }

            foreach ($indices as $idx) {
                $resourceMap[$idx] = $updatedAt;
            }

            $cacheKey = "updated_at:{$key}:" . ($includePrerelease ? '1' : '0') . ":{$sourceType}";
            if ($updatedAt !== null) {
                file_cache_set($cacheKey, $updatedAt, RESOURCE_UPDATE_CACHE_TTL);
            }
        }
    }

    return $resourceMap;
}

/**
 * 清空缓存
 */
function clear_cache() {
    if (!is_dir(CACHE_DIR)) {
        return;
    }
    $files = glob(CACHE_DIR . '/*.json');
    if ($files) {
        foreach ($files as $file) {
            @unlink($file);
        }
    }
}
