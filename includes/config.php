<?php
/**
 * 配置文件 - 敏感信息环境变量版
 * 从环境变量读取 GitHub Token，避免硬编码
 */

// 获取 GitHub Token 的优先级：
// 1. 环境变量 GITHUB_TOKEN
// 2. 配置文件 data/config.local.json
// 3. 空字符串（不使用 Token）

function loadSecureConfig() {
    // 基础配置
    $config = array(
        'proxyUrls' => array(
            'https://ghproxy.net/',
            'https://ghproxy.monkeyray.net/',
            'https://gproxy.mlds.dpdns.org/'
        ),
        'resources' => array()
    );
    
    // 优先从环境变量读取 GitHub Token
    $token = getenv('GITHUB_TOKEN');
    
    // 如果环境变量不存在，尝试读取本地配置文件
    if (empty($token) && file_exists(__DIR__ . '/data/config.local.json')) {
        $localConfig = @json_decode(file_get_contents(__DIR__ . '/data/config.local.json'), true);
        if (is_array($localConfig) && isset($localConfig['githubToken'])) {
            $token = $localConfig['githubToken'];
        }
    }
    
    $config['githubToken'] = $token ?: '';
    
    // 读取资源列表
    $resourcesFile = __DIR__ . '/data/resources.json';
    if (file_exists($resourcesFile)) {
        $resourcesData = @json_decode(file_get_contents($resourcesFile), true);
        if (is_array($resourcesData) && isset($resourcesData['resources'])) {
            $config['resources'] = $resourcesData['resources'];
        }
        // 兼容旧配置
        if (is_array($resourcesData) && isset($resourcesData['proxyUrls'])) {
            $config['proxyUrls'] = $resourcesData['proxyUrls'];
        }
    }
    
    return $config;
}
