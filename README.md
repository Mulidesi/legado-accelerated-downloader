# Legado 资源加速下载站点

基于 PHP 的 GitHub Release 聚合下载站点，用于集中展示 Legado 相关资源，并通过配置的 HTTPS 加速代理生成下载入口。项目采用单入口 PHP 架构，适合部署在支持 PHP 和 cURL 的虚拟主机、Apache、Nginx 或轻量 PHP 运行环境中。

[![Version](https://img.shields.io/badge/version-1.9.0-blue.svg)](https://github.com)
[![PHP](https://img.shields.io/badge/PHP-7.4+-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

## 功能特性

- 使用 `data/resources.json` 管理资源列表、代理地址和首页跑马灯。
- 调用 GitHub API 获取 release、仓库 Stars、Forks 和更新时间。
- 支持 Tag 数据源，可通过 `sourceType: "tag"` 配置使用 GitHub Tags API。
- 支持预发布版本筛选，可按资源单独配置 `usePrerelease`。
- 支持 Android、iOS、Windows、HarmonyOS、macOS、Linux 平台展示和筛选。
- 资源可配置 `platforms` 字段，首页优先使用配置值，减少 GitHub API 请求。
- 支持推荐资源置顶展示。
- 支持多组 HTTPS 下载代理，并限制代理域名 allowlist。
- 首页展示每个资源的最近更新时间，与详情页保持一致。
- 详情页展示最近 release/tag、资源文件、平台标签和加速下载按钮。
- Tag 模式支持源码下载（zipball/tarball）。
- 文件缓存降低 GitHub API 调用量，减少重复请求。
- 相同 GitHub 仓库的批量查询自动去重，避免冗余 API 调用。
- GitHub 仓库详情和 release 信息并发请求，提升详情页加载速度。
- GitHub Token 支持环境变量和本地配置文件，Token 失效时自动匿名降级。
- Material Design 3 风格 UI，支持明暗主题切换和移动端访问。
- 卡片入场动画和骨架屏 shimmer 效果，减少白屏等待感。
- 图片原生懒加载，减少首屏请求数。
- 筛选按钮无障碍适配（ARIA），支持屏幕阅读器。
- 零内联样式，全部 CSS 语义化类管理。
- 内置安全响应头、CSP、TLS 校验、外链隔离和敏感目录访问保护。

## 环境要求

- PHP 7.4+
- PHP cURL 扩展
- PHP JSON 扩展
- 服务器允许访问 GitHub API
- 服务器 CA 证书链可用，用于 HTTPS TLS 校验

## 快速开始

### 本地预览

```bash
# 启动 PHP 内置服务器
php -S localhost:8000
```

访问：`http://localhost:8000/`

### 部署到服务器

1. 上传 `legado-deploy-v1.8.3.zip` 到服务器并解压，或直接上传项目文件到 Web 根目录。
2. 根据需要编辑 `data/resources.json`。
3. 配置 GitHub Token。
4. 确认 Web 服务器可写入 `data/cache/`。
5. 访问 `index.php` 或站点首页。

## 配置说明

主要配置文件位于 `data/resources.json`：

```json
{
    "proxyUrls": [
        "https://ghproxy.net/",
        "https://ghproxy.monkeyray.net/",
        "https://gproxy.mlds.dpdns.org/"
    ],
    "marquee": {
        "enabled": true,
        "items": [
            {
                "text": "欢迎访问 Legado 资源加速下载站，点击查看开源阅读项目 Legado。",
                "url": "https://github.com/gedoor/legado"
            }
        ]
    },
    "resources": [
        {
            "name": "阅读 Archive",
            "owner": "Rimchars",
            "repo": "legado",
            "description": "阅读 Archive 继承自 Lyc 维护的 Legado 分支。",
            "platforms": ["Android"],
            "usePrerelease": true,
            "recommended": true
        },
        {
            "name": "示例 Tag 项目",
            "owner": "github-owner",
            "repo": "repo-name",
            "description": "编译资源在 Tag 中的项目示例。",
            "sourceType": "tag",
            "usePrerelease": true
        }
    ]
}
```

### 顶层字段

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `proxyUrls` | array | 是 | 下载加速代理地址列表。当前代码仅接受 allowlist 内的 HTTPS 域名。 |
| `marquee` | object | 否 | 首页公告跑马灯配置。 |
| `resources` | array | 是 | GitHub 资源列表。 |

### marquee 字段

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `enabled` | boolean | 否 | 是否启用首页跑马灯。 |
| `items` | array | 否 | 跑马灯条目，最多展示 10 条。 |
| `items[].text` | string | 是 | 公告文本，最多保留 160 个 UTF-8 字符。 |
| `items[].url` | string | 否 | 公告链接，仅接受 HTTPS URL。 |

### resources 字段

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `name` | string | 是 | 资源显示名称。 |
| `owner` | string | 是 | GitHub 仓库拥有者。 |
| `repo` | string | 是 | GitHub 仓库名称。 |
| `description` | string | 是 | 资源简介。 |
| `platforms` | array | 否 | 平台标签，例如 `Android`、`Windows`、`macOS`、`Linux`。首页筛选优先使用该字段。 |
| `usePrerelease` | boolean | 是 | 是否包含 prerelease 版本。 |
| `recommended` | boolean | 否 | 是否推荐，推荐资源会置顶展示。 |
| `sourceType` | string | 否 | 数据源类型：`release`（默认）使用 Release API；`tag` 使用 Tag API（适用于编译资源在 Tag 中的项目）。 |

### 代理地址限制

当前允许的代理域名：

- `ghproxy.net`
- `ghproxy.monkeyray.net`
- `gproxy.mlds.dpdns.org`

配置项必须使用 HTTPS。非法代理地址会被过滤；全部过滤后会回退到默认代理列表。

## GitHub Token 配置

配置 Token 可以提升 GitHub API 请求额度，从公共 API 的 60 次/小时提升到认证 API 的 5000 次/小时。

### 环境变量

推荐在虚拟主机控制面板或运行环境中配置：

```bash
# 设置 GitHub API Token
export GITHUB_TOKEN=ghp_xxxxxxxxxxxxxxxxxxxx
```

Apache 环境也可以在 `.htaccess` 中配置：

```apache
SetEnv GITHUB_TOKEN ghp_xxxxxxxxxxxxxxxxxxxx
```

### 本地配置文件

复制示例配置：

```bash
# 复制本地配置模板
cp data/config.local.json.example data/config.local.json
```

编辑 `data/config.local.json`：

```json
{
    "githubToken": "ghp_xxxxxxxxxxxxxxxxxxxx"
}
```

`data/config.local.json` 已加入 `.gitignore`。当前仓库中如已有该文件被 Git 跟踪，建议按仓库策略从索引中移出，并轮换曾经提交或暴露过的 Token。

## 使用说明

1. 首页展示全部资源卡片，支持按平台筛选。
2. 推荐资源会优先展示。
3. 资源卡片展示名称、简介、平台、最近更新时间和详情入口。
4. 详情页展示仓库信息、最近 release 和资源文件列表。
5. 点击加速下载按钮后，系统会用配置的代理地址拼接 GitHub 文件地址。

## 平台识别

资源配置包含 `platforms` 时，首页优先使用配置的平台标签。缺少 `platforms` 时，系统会根据 release asset 文件名识别平台。

| 平台 | 检测关键词 |
|------|-----------|
| Android | `.apk`、`arm64`、`armeabi`、`android` |
| iOS | `.ipa`、`ios` |
| Windows | `win`、`.exe`、`.msi` |
| HarmonyOS | `harmony`、`hms` |
| macOS | `mac`、`.dmg` |
| Linux | `linux`、`.deb`、`.rpm` |

## 缓存机制

项目使用文件缓存减少 GitHub API 调用：

| 缓存内容 | 默认 TTL |
|----------|----------|
| GitHub API 通用响应 | 15 分钟 |
| Release 列表 | 15 分钟 |
| 仓库详情 | 1 小时 |
| 平台识别结果 | 6 小时 |
| 资源最近更新时间 | 1 小时 |

缓存文件存储在 `data/cache/`。需要强制刷新时，清空该目录中的缓存文件即可。

首页多个资源指向同一 GitHub 仓库时自动合并查询，避免重复请求。

## 安全设计

- `index.php` 输出安全响应头：`X-Content-Type-Options`、`X-Frame-Options`、`Referrer-Policy`、`Permissions-Policy`、`Content-Security-Policy`。
- CSP 限制默认资源来源为 `self`，并启用 `frame-ancestors 'none'`、`form-action 'self'`、`base-uri 'self'`。
- Apache `.htaccess` 禁止直接访问 `includes/` 和 `data/` 目录。
- 所有 include 文件顶部添加 `DATA_DIR` 守卫检查，直接访问返回 403。
- cURL 启用 `CURLOPT_SSL_VERIFYPEER` 和 `CURLOPT_SSL_VERIFYHOST`，保持 HTTPS 证书校验。
- `proxyUrls` 仅接受 HTTPS 和 allowlist 域名。
- `marquee.items[].url` 仅接受 HTTPS URL。
- 外部链接使用 `target="_blank"` 时同步设置 `rel="noopener noreferrer"`。
- `.htaccess` 提供 Apache 环境下的敏感文件访问保护和安全头兜底。
- `data/.htaccess` 阻止直接访问 `data/` 目录。

## 项目结构

```text
github-accel-downloader/
├── index.php                     # 主入口、路由、安全响应头、首页和详情页数据组装
├── includes/
│   ├── config.php                # 配置加载、代理 URL 校验、跑马灯配置清洗
│   ├── functions.php             # GitHub API、平台识别、release 规范化、格式化函数
│   └── cache.php                 # 文件缓存、并发 API 请求、平台和更新时间批量获取
├── data/
│   ├── resources.json            # 资源、代理和跑马灯配置
│   ├── config.local.json.example # 本地敏感配置模板
│   ├── config.local.json         # 本地敏感配置，生产环境自行创建
│   ├── .htaccess                 # Apache data 目录访问保护
│   └── cache/                    # 运行时缓存目录
├── templates/
│   ├── home.php                  # 首页模板、跑马灯、资源卡片、平台筛选
│   └── resource.php              # 详情页模板、release 和下载列表
├── assets/
│   ├── bootstrap.min.css         # Bootstrap 5.3 样式
│   ├── bootstrap.bundle.min.js   # Bootstrap JS
│   ├── material-theme.css        # Material Design 3 主题样式（含玻璃态、骨架屏效果）
│   ├── theme-switcher.js         # 明暗主题切换
│   ├── favicon.ico               # 网站图标
│   └── github-icon.png           # GitHub 图标
├── shared.css                    # 已废弃，合并至 material-theme.css
├── .htaccess                     # Apache 访问控制和安全头兜底
├── .gitignore                    # 本地配置、缓存和日志忽略规则
├── SECURITY_MIGRATION.md         # Token 安全迁移指南
└── README.md                     # 项目说明
```

## 验证命令

```bash
# 检查 PHP 语法
php -l index.php
php -l includes/config.php
php -l includes/functions.php
php -l includes/cache.php
php -l templates/home.php
php -l templates/resource.php

# 校验资源配置 JSON
php -r 'json_decode(file_get_contents("data/resources.json")); exit(json_last_error() === JSON_ERROR_NONE ? 0 : 1);'

# 检查 Git 差异中的空白问题
git diff --check

# 检查首页响应头
curl -I -s --max-time 15 "http://localhost:8000/"

# 检查首页状态码
curl -s -o /dev/null -w "%{http_code}" --max-time 15 "http://localhost:8000/"

# 检查详情页状态码
curl -s -o /dev/null -w "%{http_code}" --max-time 15 "http://localhost:8000/?resource=legado"
```

## 常见问题

### Token 无效或额度仍然较低

1. 检查 `GITHUB_TOKEN` 环境变量是否在 PHP 运行环境中可见。
2. 检查 `data/config.local.json` 是否为有效 JSON。
3. 使用细粒度 Token，并授予公开仓库读取权限。
4. Token 返回 `401 Bad credentials` 时，系统会自动使用匿名请求继续加载公开资源。

### 页面返回 500

1. 检查 PHP 版本和 cURL 扩展。
2. 检查 `data/resources.json` JSON 格式。
3. 检查 `data/cache/` 是否可写。
4. 查看 Web 服务器错误日志。

### 资源平台未显示完整

1. 优先在资源配置中填写 `platforms`。
2. 检查 GitHub release asset 文件名是否包含可识别的平台关键词。
3. 清空 `data/cache/` 后重新访问页面。

### HTTPS 请求失败

1. 检查服务器是否安装 CA 证书包。
2. 检查虚拟主机是否允许访问 `api.github.com`。
3. 检查代理地址是否位于 allowlist 且使用 HTTPS。

## 部署注意事项

- 生产环境建议配置 GitHub Token，降低 API 限流影响。
- Apache 环境会读取项目自带 `.htaccess`；Nginx 需要在站点配置中阻止直接访问 `data/`。
- `data/cache/` 是运行时目录，适合加入部署持久化目录或保持 Web 用户可写。
- `data/config.local.json`、`data/cache/` 和日志文件属于本地运行数据，避免提交到代码仓库。
- 本项目仅聚合公开 GitHub Release 下载入口，应用版权归原作者所有。

## 更新日志

### v1.9.0 - Tag 数据源支持与时间显示修复

- 新增 Tag 数据源支持，可通过 `sourceType: "tag"` 配置使用 GitHub Tags API。
- Tag 模式支持源码下载（zipball/tarball），详情页标题显示"最近 Tag"。
- 修复版本列表排序问题，严格按发布时间降序排列。
- 修复发布时间显示问题，使用 DateTime 类正确解析 ISO 8601 格式并转换为北京时间。
- 修复首页与详情页更新时间不一致问题，统一使用 `usePrerelease` 配置过滤。
- 修复详情页时钟 SVG 图标显示异常。
- 设置默认时区为 Asia/Shanghai。

### v1.8.3 - UI 全面检修与无障碍优化

- 全部内联样式迁移至语义化 CSS 类，移除 17+ 处分散的 `style=""` 属性。
- `includes/` 目录添加 Apache 访问控制和 PHP 守卫检查，直接访问返回 403。
- 删除重复 CSS 声明（`.resource-card.recommended::before` 冲突块）。
- 修复硬编码颜色，改用主题 CSS 变量，添加深色模式全覆盖配色。
- 移除 `!important` 声明。
- 新增 15 个语义化 CSS 类（`.release-description`、`.download-icon`、`.footer-text`、`.footer-link`、`.asset-item-content`、`.asset-item-filename` 等）。
- 资源卡片改用 `min-height` 自适应高度，长文字不再截断。
- 移除冗余的 `downloadFile()` JS 函数。
- 卡片入场 fade-in-up 动画，逐张渐入（0.00s ~ 0.45s 延迟）。
- 新增骨架屏 shimmer 扫光效果（首次渲染播放一次），支持深色模式。
- 筛选按钮添加 `aria-pressed`、`role="group"` 和结果播报区域。
- 所有图片添加 `loading="lazy"`，首屏 Logo 除外。
- SVG 图标添加 `flex-shrink: 0; display: block;`，防止 flex 裁剪。

### v1.8.2 - 代码精简与性能优化

- 移除请求级内存缓存层，简化缓存体系。
- 提取公共 cURL 工厂函数，消除 3 处重复的 cURL 配置代码。
- 首页批量查询按 `owner/repo` 自动去重，相同仓库只请求一次 GitHub API。
- 添加 HTTP `Cache-Control` 响应头，减少浏览器重复请求。
- 合并共享样式文件，消除多份重复 CSS 定义。
- 消除 cURL Multi 请求中 Token 降级的双重 fallback 逻辑。

### v1.8.0 - 性能与安全增强

- 首页新增跑马灯配置和展示。
- 首页资源卡片新增最近更新时间。
- 平台筛选优先使用资源配置中的 `platforms`。
- 详情页 GitHub 数据改为并发获取。
- GitHub Token 失效时支持匿名降级。
- 恢复 cURL TLS 证书校验。
- 新增安全响应头、CSP、代理 allowlist、外链隔离和敏感目录保护。
- 移除 Google Fonts 外部依赖，改用系统字体栈。

### v1.7.3 - Token 安全增强

- 支持环境变量存储 GitHub Token。
- 支持本地配置文件 `data/config.local.json`。
- 添加 `.gitignore` 保护本地配置、缓存和日志。
- 提供 `SECURITY_MIGRATION.md` 迁移说明。

### v1.7.x

- 添加多平台检测和筛选功能。
- 优化平台检测逻辑。
- 添加推荐资源置顶功能。
- 并发获取资源平台信息。

### v1.6.x

- 添加明暗主题切换功能。
- 升级 Material Design 3 风格 UI。
- 优化缓存机制。

## 许可证

MIT License

## 鸣谢

感谢 Legado 生态和相关开源项目贡献者。本项目仅为聚合下载页面，应用版权归原作者所有。
