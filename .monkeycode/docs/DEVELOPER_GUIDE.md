# 开发者指南

## 开发环境

### 环境要求

- PHP 7.4+
- cURL 扩展
- Git（版本控制）

### 本地开发

1. **克隆项目**
   ```bash
   git clone <repository-url>
   cd github-accel-downloader
   ```

2. **启动 PHP 内置服务器**
   ```bash
   php -S localhost:8000
   ```

3. **配置资源**
   
   编辑 `data/resources.json` 添加测试资源

4. **访问应用**
   
   浏览器访问 `http://localhost:8000`

## 代码规范

### PHP 编码规范

1. **命名规范**
   - 函数名：小驼峰 `getGitHubReleases()`
   - 变量名：小驼峰 `$proxyUrl`
   - 常量名：全大写 `CONFIG_FILE`

2. **注释规范**
   - 所有函数必须有 DocBlock
   - 说明参数类型和返回值

3. **错误处理**
   - 使用 `json_last_error()` 检查 JSON 解析
   - 使用 `curl_error()` 检查网络错误
   - 返回结构化错误信息

### 示例代码

#### 添加新的辅助函数

```php
/**
 * 函数说明
 * @param 类型 $参数名 参数说明
 * @return 类型 返回值说明
 */
function newFunction($param) {
    // 实现
}
```

## 配置指南

### 添加新资源

编辑 `data/resources.json`：

```json
{
    "proxyUrl": "https://ghproxy.net/",
    "resources": [
        {
            "name": "新资源名称",
            "owner": "GitHub 用户名",
            "repo": "仓库名",
            "description": "资源简介",
            "usePrerelease": false
        }
    ]
}
```

### 切换加速代理

修改 `proxyUrl` 字段：

```json
{
    "proxyUrl": "https://ghproxy.com/"
}
```

**可用代理服务**:
- `https://ghproxy.net/`
- `https://ghproxy.com/`
- `https://github.moeyy.xyz/`

## 部署指南

### 虚拟主机部署

1. **上传文件**
   
   使用 FTP 或主机商提供的文件管理器上传所有文件

2. **设置权限**
   
   确保 `data/` 目录可读（通常默认即可）

3. **访问应用**
   
   访问 `http://your-domain.com/index.php`

### 注意事项

1. **PHP 版本**
   - 确认主机支持 PHP 7.4+
   - 检查是否启用 cURL 扩展

2. **外部访问**
   - 确认主机允许访问 GitHub API
   - 部分免费主机可能限制外部 HTTP 请求

3. **速率限制**
   - GitHub 未认证 API 限制 60 次/小时
   - 建议配置 GitHub Personal Access Token 提高限制

## 调试技巧

### 启用错误显示

开发环境可临时启用错误显示：

```php
// 在 index.php 开头添加
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

### 查看 API 响应

临时打印 GitHub API 原始响应：

```php
// 在 getGitHubReleases() 中添加
error_log('GitHub API Response: ' . $response);
```

### 测试 API 速率限制

使用 curl 手动测试：

```bash
curl -I https://api.github.com/repos/owner/repo/releases
# 查看 X-RateLimit-Remaining 头
```

## 常见问题

### Q: GitHub API 返回 403 错误

**A**: 可能原因：
1. 速率限制已达到（等待 1 小时）
2. 仓库不存在或无权限
3. 主机被 GitHub 屏蔽

**解决方案**:
- 使用 GitHub Personal Access Token（在请求头添加认证）
- 更换加速代理服务

### Q: 下载链接无法访问

**A**: 可能原因：
1. 加速代理服务不可用
2. 仓库 release 没有附加文件（assets）

**解决方案**:
- 尝试其他加速代理
- 检查 GitHub 原仓库是否有 release assets

### Q: 页面显示空白

**A**: 可能原因：
1. PHP 语法错误
2. 配置文件格式错误

**解决方案**:
- 查看 PHP 错误日志
- 使用 JSON 验证工具检查 `resources.json`

## 扩展开发

### 添加新的加速代理

在 `data/resources.json` 中配置即可，无需修改代码：

```json
{
    "proxyUrl": "https://your-proxy-service.com/"
}
```

### 添加更多 release

修改 `getGitHubReleases()` 中的 `per_page` 参数：

```php
$apiUrl = "https://api.github.com/repos/{$owner}/{$repo}/releases?per_page=10";
```

### 添加缓存机制

可使用文件缓存减少 API 调用：

```php
function getCachedReleases($owner, $repo, $ttl = 3600) {
    $cacheFile = DATA_DIR . "/cache/{$owner}-{$repo}.json";
    
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
        return json_decode(file_get_contents($cacheFile), true);
    }
    
    $releases = getGitHubReleases($owner, $repo);
    
    // 保存缓存
    file_put_contents($cacheFile, json_encode($releases));
    
    return $releases;
}
```

## 版本发布

### 发布流程

1. 更新 `README.md` 版本号
2. 提交代码
3. 在 GitHub 创建 release

### 版本命名

遵循语义化版本规范：`主版本。次版本。修订号`

- `1.0.0`: 初始发布
- `1.0.1`: Bug 修复
- `1.1.0`: 新功能

## 贡献指南

### 提交 Pull Request

1. Fork 项目
2. 创建功能分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 创建 Pull Request

### 报告 Bug

提供以下信息：
- 复现步骤
- 预期行为
- 实际行为
- 环境信息（PHP 版本、主机商）

## 资源

- [GitHub REST API 文档](https://docs.github.com/en/rest)
- [Bootstrap 5 文档](https://getbootstrap.com/docs/5.3/)
- [PHP cURL 手册](https://www.php.net/manual/zh/book.curl.php)
