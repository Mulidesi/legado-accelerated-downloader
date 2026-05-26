# 接口文档

## 配置数据结构

### ResourcesConfig

系统主配置结构。

```php
array(
    'proxyUrl' => string,        // 加速代理基础 URL
    'resources' => Resource[]    // 资源列表
)
```

### Resource

单个 GitHub 资源配置。

```php
array(
    'name' => string,            // 资源显示名称（必填）
    'owner' => string,           // GitHub 仓库拥有者（必填）
    'repo' => string,            // GitHub 仓库名（必填）
    'description' => string,     // 资源简介（必填）
    'usePrerelease' => boolean   // 是否包含预发布版本（必填）
)
```

**字段约束**:
- `name`: 1-100 字符
- `owner`: 有效的 GitHub 用户名
- `repo`: 有效的 GitHub 仓库名
- `description`: 0-500 字符
- `usePrerelease`: true 或 false

### ValidationRuleResult

验证结果结构。

```php
array(
    'valid' => boolean,          // 验证是否通过
    'errors' => string[]         // 错误信息列表
)
```

## GitHub API 数据结构

### GitHubRelease

GitHub API 返回的 release 对象。

```php
array(
    'id' => int,                 // Release ID
    'tag_name' => string,        // 版本标签
    'name' => string,            // Release 名称
    'body' => string,            // 更新日志（Markdown 格式）
    'published_at' => string,    // 发布时间（ISO 8601）
    'prerelease' => boolean,     // 是否预发布
    'assets' => GitHubAsset[]    // 资源文件列表
)
```

### GitHubAsset

Release 中的资源文件。

```php
array(
    'name' => string,            // 文件名
    'size' => int,               // 文件大小（字节）
    'browser_download_url' => string  // 下载 URL
)
```

### GitHubAPIError

GitHub API 错误响应。

```php
array(
    'error' => string,           // 错误类型：network|ratelimit|notfound|unknown|parse
    'message' => string          // 错误信息
)
```

**错误类型说明**:

| 错误类型 | HTTP 码 | 说明 |
|----------|---------|------|
| network | - | 网络连接错误 |
| ratelimit | 403 | API 速率限制 |
| notfound | 404 | 仓库不存在 |
| unknown | 其他 | 未知错误 |
| parse | - | JSON 解析失败 |

## 函数接口

### getConfig()

读取配置文件。

**签名**:
```php
function getConfig(): ?array
```

**返回值**:
- 成功：ResourcesConfig 数组
- 失败：null（文件不存在或 JSON 格式错误）

### getResource()

根据 owner 和 repo 查找资源。

**签名**:
```php
function getResource(array $config, string $owner, string $repo): ?array
```

**参数**:
- `$config`: 配置数组
- `$owner`: 仓库拥有者
- `$repo`: 仓库名

**返回值**:
- 成功：Resource 数组
- 失败：null

### validateResource()

验证资源字段完整性。

**签名**:
```php
function validateResource(array $resource): array
```

**参数**:
- `$resource`: 资源数组

**返回值**:
- ValidationRuleResult 结构

### getGitHubReleases()

调用 GitHub API 获取 release 列表。

**签名**:
```php
function getGitHubReleases(string $owner, string $repo, bool $includePrerelease = false): array
```

**参数**:
- `$owner`: 仓库拥有者
- `$repo`: 仓库名
- `$includePrerelease`: 是否包含预发布版本（默认 false）

**返回值**:
- 成功：GitHubRelease[] 数组（最多 3 个）
- 失败：GitHubAPIError 结构

**行为**:
- 自动过滤预发布版本（当 `$includePrerelease=false` 时）
- 返回数组按时间倒序（最新在前）
- 最多返回 3 个 release

### buildAcceleratedUrl()

构建加速下载链接。

**签名**:
```php
function buildAcceleratedUrl(string $proxyUrl, string $githubUrl): string
```

**参数**:
- `$proxyUrl`: 代理基础 URL（如 `https://ghproxy.net/`）
- `$githubUrl`: GitHub 原始下载地址

**返回值**:
- 加速下载完整 URL

**示例**:
```php
buildAcceleratedUrl('https://ghproxy.net/', 'https://github.com/owner/repo/releases/download/v1.0/app.apk')
// 返回：https://ghproxy.net/https://github.com/owner/repo/releases/download/v1.0/app.apk
```

### formatFileSize()

格式化文件大小。

**签名**:
```php
function formatFileSize(int $bytes): string
```

**参数**:
- `$bytes`: 字节数

**返回值**:
- 格式化字符串（如 "1.5 MB"）

**转换规则**:
- < 1024: `X B`
- < 1024²: `X.KB KB`
- < 1024³: `X.KB MB`
- >= 1024³: `X.KB GB`

### formatDate()

格式化日期。

**签名**:
```php
function formatDate(string $date): string
```

**参数**:
- `$date`: ISO 8601 日期字符串

**返回值**:
- 格式化字符串（`Y-m-d H:i` 格式）

**示例**:
```php
formatDate('2024-01-15T10:30:00Z')
// 返回：2024-01-15 10:30
```

### getRoute()

获取当前路由。

**签名**:
```php
function getRoute(): string
```

**返回值**:
- `'home'`: 首页
- `'resource'`: 资源详情页

### render()

渲染模板。

**签名**:
```php
function render(string $template, array $data = []): void
```

**参数**:
- `$template`: 模板文件名（不含路径）
- `$data`: 模板数据（键将转为模板变量）

**行为**:
- 从 `TEMPLATES_DIR` 加载模板
- 使用 `extract()` 将数据转为局部变量
- 直接输出 HTML

## 路由参数

### 首页路由

**路径**: `/` 或 `index.php`

**参数**: 无

**模板变量**:
```php
array(
    'config' => ResourcesConfig,
    'resources' => Resource[]
)
```

### 资源详情路由

**路径**: `/resource` 或 `index.php?owner=X&repo=Y`

**GET 参数**:
| 参数 | 必填 | 说明 |
|------|------|------|
| owner | 是 | 仓库拥有者 |
| repo | 是 | 仓库名 |

**模板变量**:
```php
array(
    'config' => ResourcesConfig,
    'resource' => Resource,
    'releases' => GitHubRelease[]|GitHubAPIError
)
```

## 错误响应

### HTTP 状态码

| 状态码 | 场景 | 响应内容 |
|--------|------|----------|
| 200 | 成功 | HTML 页面 |
| 400 | 参数缺失 | 错误提示 HTML |
| 404 | 资源不存在 | 错误提示 HTML |
| 500 | 配置错误 | 错误提示 HTML |

### 页面错误提示

详情页通过页面内容显示错误（不返回错误状态码）：

- API 速率限制：警告框（黄色）
- 仓库不存在：错误框（红色）
- 网络错误：错误框（红色）
- 无 release：信息框（蓝色）

## 常量定义

```php
define('DATA_DIR', __DIR__ . '/data');
define('TEMPLATES_DIR', __DIR__ . '/templates');
define('CONFIG_FILE', DATA_DIR . '/resources.json');
```
