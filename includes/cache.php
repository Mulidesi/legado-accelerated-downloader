<?php
/**
 * 文件缓存系统 - 兼容性修复版
 */

// 缓存目录定义（如果未定义）
if (!defined('CACHE_DIR')) {
    define('CACHE_DIR', __DIR__ . '/../data/cache');
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
function github_api_multi_request($urls, $timeout = 15) {
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
            $results[$key] = github_api_single_request($url, $timeout);
        }
        return $results;
    }
    
    // 并发请求
    $mh = curl_multi_init();
    if ($mh === false) {
        // 并发失败，降级为顺序请求
        foreach ($urls_to_fetch as $key => $url) {
            $results[$key] = github_api_single_request($url, $timeout);
        }
        return $results;
    }
    
    $chs = array();
    $headers = getGitHubApiHeaders();
    
    foreach ($urls_to_fetch as $key => $url) {
        $ch = curl_init();
        if ($ch === false) {
            continue;
        }
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ));
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
        $execResult = curl_multi_exec($mh, $running);
        if ($execResult !== CURLM_OK) {
            break;
        }
        if ($running > 0) {
            curl_multi_select($mh, 0.1);
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
                file_cache_set('api:' . md5($urls_to_fetch[$key]), $data, 3600);
            } else {
                $results[$key] = null;
            }
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
function github_api_single_request($url, $timeout = 15) {
    $cacheKey = 'api:' . md5($url);
    $cached = file_cache_get($cacheKey);
    if ($cached !== null) {
        return $cached;
    }
    
    $ch = curl_init();
    if ($ch === false) {
        return null;
    }
    
    curl_setopt_array($ch, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => getGitHubApiHeaders(),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if (is_array($data)) {
            file_cache_set($cacheKey, $data, 3600);
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
    
    // 构建请求列表
    $urls = array();
    $resourceMap = array();
    
    foreach ($resources as $index => $resource) {
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
        
        $url = "https://api.github.com/repos/{$key}/releases?per_page=3";
        $urls[$index] = $url;
        $resourceMap[$index] = null;
    }
    
    // 并发请求
    if (!empty($urls)) {
        $responses = github_api_multi_request($urls);
        
        foreach ($responses as $index => $data) {
            $platforms = array();
            
            if (is_array($data)) {
                foreach ($data as $release) {
                    $assets = isset($release['assets']) ? $release['assets'] : array();
                    foreach ($assets as $asset) {
                        $name = isset($asset['name']) ? $asset['name'] : '';
                        $platforms = array_merge($platforms, detectPlatforms($name));
                    }
                }
            }
            
            // 回退检测
            if (empty($platforms) && isset($resources[$index]['repo'])) {
                $lower = strtolower($resources[$index]['repo']);
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
            
            $platforms = array_values(array_unique($platforms));
            $resourceMap[$index] = $platforms;
            
            // 写入缓存
            if (isset($resources[$index]['owner']) && isset($resources[$index]['repo'])) {
                $key = $resources[$index]['owner'] . '/' . $resources[$index]['repo'];
                file_cache_set("platforms:{$key}", $platforms, 3600);
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
