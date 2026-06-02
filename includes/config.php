<?php
/**
 * 配置文件 - 敏感信息环境变量版
 * 从环境变量读取 GitHub Token，避免硬编码
 */

// 获取 GitHub Token 的优先级：
// 1. 环境变量 GITHUB_TOKEN
// 2. 配置文件 data/config.local.json
// 3. 空字符串（不使用 Token）

function isAllowedUrl($url, $allowedHosts = array()) {
    if (!is_string($url)) {
        return false;
    }

    $url = trim($url);
    if ($url === '' || strlen($url) > 2048) {
        return false;
    }

    $parts = parse_url($url);
    if (!is_array($parts) || !isset($parts['scheme']) || !isset($parts['host'])) {
        return false;
    }

    if (strtolower($parts['scheme']) !== 'https') {
        return false;
    }

    if (empty($allowedHosts)) {
        return true;
    }

    return in_array(strtolower($parts['host']), $allowedHosts, true);
}

function sanitizeProxyUrls($urls) {
    $defaultProxyUrls = array(
        'https://ghproxy.net/',
        'https://ghproxy.monkeyray.net/',
        'https://gproxy.mlds.dpdns.org/'
    );
    $allowedHosts = array('ghproxy.net', 'ghproxy.monkeyray.net', 'gproxy.mlds.dpdns.org');
    $source = is_array($urls) ? $urls : array();
    $result = array();

    foreach ($source as $url) {
        if (!isAllowedUrl($url, $allowedHosts)) {
            continue;
        }
        $result[] = rtrim(trim($url), '/') . '/';
    }

    return empty($result) ? $defaultProxyUrls : array_values(array_unique($result));
}

function sanitizeMarqueeConfig($marquee) {
    $result = array(
        'enabled' => false,
        'items' => array()
    );

    if (!is_array($marquee)) {
        return $result;
    }

    $result['enabled'] = !empty($marquee['enabled']);
    $items = isset($marquee['items']) && is_array($marquee['items']) ? $marquee['items'] : array();

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $text = isset($item['text']) ? trim((string)$item['text']) : '';
        if ($text === '') {
            continue;
        }

        $url = isset($item['url']) ? trim((string)$item['url']) : '';
        $result['items'][] = array(
            'text' => function_exists('mb_substr') ? mb_substr($text, 0, 160, 'UTF-8') : substr($text, 0, 480),
            'url' => isAllowedUrl($url) ? $url : ''
        );

        if (count($result['items']) >= 10) {
            break;
        }
    }

    return $result;
}

function loadSecureConfig() {
    $dataDir = defined('DATA_DIR') ? DATA_DIR : __DIR__ . '/../data';
    $localConfigFile = $dataDir . '/config.local.json';
    $resourcesFile = $dataDir . '/resources.json';

    // 基础配置
    $config = array(
        'proxyUrls' => array(
            'https://ghproxy.net/',
            'https://ghproxy.monkeyray.net/',
            'https://gproxy.mlds.dpdns.org/'
        ),
        'marquee' => array(
            'enabled' => false,
            'items' => array()
        ),
        'resources' => array(),
        'legacyTokenInResources' => false
    );
    
    // 优先从环境变量读取 GitHub Token
    $token = getenv('GITHUB_TOKEN');
    
    // 如果环境变量不存在，尝试读取本地配置文件
    if (empty($token) && file_exists($localConfigFile)) {
        $localConfig = @json_decode(file_get_contents($localConfigFile), true);
        if (is_array($localConfig) && isset($localConfig['githubToken'])) {
            $token = $localConfig['githubToken'];
        }
    }
    
    $config['githubToken'] = $token ?: '';
    
    // 读取资源列表
    if (file_exists($resourcesFile)) {
        $resourcesData = @json_decode(file_get_contents($resourcesFile), true);
        if (is_array($resourcesData) && isset($resourcesData['resources'])) {
            $config['resources'] = $resourcesData['resources'];
        }
        if (is_array($resourcesData) && isset($resourcesData['marquee']) && is_array($resourcesData['marquee'])) {
            $config['marquee'] = sanitizeMarqueeConfig($resourcesData['marquee']);
        }
        if (is_array($resourcesData) && !empty($resourcesData['githubToken'])) {
            $config['legacyTokenInResources'] = true;
        }
        // 兼容旧配置
        if (is_array($resourcesData) && isset($resourcesData['proxyUrls'])) {
            $config['proxyUrls'] = sanitizeProxyUrls($resourcesData['proxyUrls']);
        }
    }
    $config['proxyUrls'] = sanitizeProxyUrls($config['proxyUrls']);
    
    return $config;
}
