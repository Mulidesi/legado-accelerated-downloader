# Legado 资源加速下载站点

基于 PHP 的 GitHub Release 聚合下载站点，用于集中展示 Legado 相关资源，并通过配置的 HTTPS 加速代理生成下载入口。项目采用单入口 PHP 架构，适合部署在支持 PHP 和 cURL 的虚拟主机、Apache、Nginx 或轻量 PHP 运行环境中。

[![Version](https://img.shields.io/badge/version-1.13.0-blue.svg)](https://github.com)
[![PHP](https://img.shields.io/badge/PHP-7.4+-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

## 功能特性

- 使用 `data/resources.json` 管理资源列表、代理地址、分类和首页跑马灯。
- 支持资源分类分组展示，可在配置文件中自定义分类顺序。
- 支持自定义仓库临时加速下载，无需预先配置即可查看任意 GitHub 仓库的 release。
- 调用 GitHub API 获取 release、仓库 Stars、Forks 和更新时间。
- 支持 Tag 数据源，可通过 `sourceType: "tag"` 配置使用 GitHub Tags API。
- 支持预发布版本筛选，可按资源单独配置 `usePrerelease`。
- 支持 Android、iOS、Windows、HarmonyOS、macOS、Linux 平台展示和筛选。
- 资源可配置 `platforms` 字段，首页优先使用配置值，减少 GitHub API 请求。
- 支持推荐资源在所属分类内置顶展示。
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
- **虚拟主机全面兼容**：`curl_multi_exec` 禁用时自动降级顺序请求，`chmod` 禁用时跳过权限设置，错误日志跟随主机策略。
- **静态快照兜底**：`resources.json` 可配置 Stars/Forks/Release 时间快照，网络受限时首页依然完整展示。
- **PWA 离线缓存**：支持离线访问和静态资源缓存，移动端体验更佳。
- **下载链接一键复制**：点击即复制加速下载链接，支持非安全上下文回退。

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

1. 从 GitHub Release 下载 `release.zip`，上传到服务器的 Web 根目录并解压，或直接上传项目文件。
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
                "text": "欢迎访问 Legado 资源加速下载站,点击查看开源阅读项目 Legado。",
                "url": "https://github.com/gedoor/legado"
            }
        ]
    },
    "categories": ["阅读", "工具"],
    "resources": [
        {
            "name": "阅读 Archive",
            "owner": "Rimchars",
            "repo": "legado",
            "category": "阅读",
            "description": "阅读 Archive 继承自 Lyc 维护的 Legado 分支。",
            "platforms": ["Android"],
            "usePrerelease": true,
            "recommended": true
        },
        {
            "name": "示例 Tag 项目",
            "owner": "github-owner",
            "repo": "repo-name",
            "category": "工具",
            "description": "编译资源在 Tag 中的项目示例。",
            "sourceType": "tag",
            "usePrerelease": true
        }
    ]
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
| `categories` | array | 否 | 分类顺序数组，例如 `["阅读", "工具"]`。未配置时首页从资源 `category` 字段动态汇总。 |
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
| `category` | string | 否 | 资源分类，例如 `阅读`、`工具`。首页按分类分组展示。 |
| `platforms` | array | 否 | 平台标签，例如 `Android`、`Windows`、`macOS`、`Linux`。首页筛选优先使用该字段。 |
| `usePrerelease` | boolean | 是 | 是否包含 prerelease 版本。 |
| `recommended` | boolean | 否 | 是否推荐，推荐资源会在所属分类内置顶展示。 |
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

### 浏览资源

1. 首页按分类分组展示资源卡片，支持按分类和平台筛选。
2. 推荐资源在所属分类内优先展示。
3. 资源卡片展示名称、简介、平台、最近更新时间和详情入口。
4. 详情页展示仓库信息、最近 release 和资源文件列表。
5. 点击加速下载按钮后，系统会用配置的代理地址拼接 GitHub 文件地址。

### 自定义下载

首页提供自定义下载入口，支持临时查看任意 GitHub 仓库的 release：

1. 在首页自定义下载框中输入仓库标识，支持以下格式：
   - `owner/repo`（如 `gedoor/legado`）
   - `https://github.com/owner/repo`
   - `http://github.com/owner/repo`
   - `github.com/owner/repo`
2. 点击"获取下载"按钮或按回车键提交。
3. 系统会打开侧栏展示该仓库的 release 信息和加速下载按钮。
4. 自定义仓库默认包含预发布版本，不会保存到配置文件。

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
├── index.php                     # 主入口、路由、安全响应头、首页和详情页数据组装；支持自定义仓库临时路由、/health 诊断端点
├── includes/
│   ├── config.php                # 配置加载、代理 URL 校验、跑马灯/分类配置清洗（sanitizeCategories）
│   ├── functions.php             # GitHub API、平台识别、release 规范化、格式化函数、HTTP 传输探测
│   ├── cache.php                 # 文件缓存、并发 API 请求、平台和更新时间批量获取、chmod 存在性检测
│   └── batch-stats.php           # 批量预取仓库 Star/Fork 与更新时间，全部失败时跳过写入避免空缓存阻塞
├── data/
│   ├── resources.json            # 资源、代理、分类和跑马灯配置（含静态快照兜底字段）
│   ├── config.local.json.example # 本地敏感配置模板
│   ├── config.local.json         # 本地敏感配置，生产环境自行创建（已被 .gitignore 排除）
│   ├── .htaccess                 # Apache data 目录访问保护
│   └── cache/                    # 运行时缓存目录
├── templates/
│   ├── home.php                  # 首页模板：分类分组卡片、分类/平台筛选、自定义下载入口、跑马灯
│   ├── detail-fragment.php       # 详情内容片段，侧栏异步加载与独立页面共用
│   └── resource.php              # 独立详情页，无 JS 时的回退入口
├── assets/
│   ├── material-theme.css        # 主题样式，含设计令牌、明暗两套色板、分类分组与自定义下载样式
│   ├── app.js                    # 侧栏、搜索、分类/平台筛选、URL 历史同步、自定义下载提交逻辑
│   ├── theme-switcher.js         # 明暗主题切换
│   ├── copy-link.js              # 下载链接一键复制，事件委托适配异步渲染
│   ├── pwa.js                    # PWA 注册与更新提示
│   ├── favicon.ico               # 浏览器标签页图标，含 16/32/48 三种尺寸
│   ├── apple-touch-icon.png      # iOS 主屏图标
│   ├── icon-192.png              # PWA 192px 图标
│   ├── icon-512.png              # PWA 512px 图标
│   ├── icon-maskable-512.png     # PWA maskable 自适应图标
│   ├── logo.png                  # 顶栏站点标识
│   ├── logo@2x.png               # 高分屏站点标识
│   └── github-icon.png           # GitHub 图标
├── manifest.webmanifest          # PWA 应用清单
├── sw.js                         # Service Worker：静态资源 cache-first，页面 network-first
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
php -l templates/detail-fragment.php

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
- 虚拟主机禁用 `curl_multi_exec` 时，系统自动降级为顺序请求；禁用 `chmod` 时跳过权限设置，均不影响功能。
- 虚拟主机错误日志策略由主机 `php.ini` 控制，项目不再强制覆盖 `log_errors` 与 `error_log`。
- 时区跟随主机 `php.ini` 配置；详情页时间通过 `formatDate()` 内部转换为北京时间展示。

## 静态快照配置

网络受限或 GitHub API 不可达时，可在 `resources.json` 的资源项中配置静态快照，首页据此渲染 Stars、Forks 与最近更新时间：

```json
{
    "name": "阅读 Archive",
    "owner": "Rimchars",
    "repo": "legado",
    "category": "阅读",
    "platforms": ["Android"],
    "snapshot": {
        "stars": 135,
        "forks": 12,
        "pushedAt": "2026-08-17T09:00:00Z"
    }
}
```

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `snapshot.stars` | integer | 否 | 静态 Stars 数值，API 不可用时兜底展示。 |
| `snapshot.forks` | integer | 否 | 静态 Forks 数值。 |
| `snapshot.pushedAt` | string | 否 | ISO 8601 格式的最近更新时间。 |

## 更新日志

### v1.13.0 - 虚拟主机环境适配与静态快照

**虚拟主机兼容性修复**

- 移除强制时区覆盖：删除 `date_default_timezone_set('Asia/Shanghai')`，改为跟随主机 `php.ini` 时区配置；`formatDate()` 内部转换到北京时间输出，避免虚拟主机配置冲突。
- 移除强制错误日志接管：删除 `ini_set('log_errors')` 与 `ini_set('error_log')`，错误日志策略完全交由主机控制，解决主机 `log_errors=off` 下的配置冲突。
- 修复 `chmod` 禁用兼容：`includes/cache.php` 增加 `function_exists('chmod')` 检测，PHP 8.3 ZTS 等禁用 `chmod` 的环境不再触发致命错误。
- 修复空缓存阻塞：`includes/batch-stats.php` 仅在至少一个仓库成功时写入缓存，全部失败时跳过，避免 null 数据持久化阻塞后续刷新。
- `curl_multi_exec` 禁用降级：共享主机禁用该函数时自动走 `sequential_fetch()` 顺序请求路径，功能不受影响。

**数据与展示增强**

- 资源配置升级：`data/resources.json` 扩充至 21 个资源（阅读/影视/音乐/工具），其中 10 个资源内置静态快照，API 不可达时首页仍可完整渲染。
- 新增静态快照兜底字段：资源项可配置 `snapshot.stars`、`snapshot.forks`、`snapshot.pushedAt`，配合运行时 API 数据使用。
- 修复 Release 时间图标布局：SVG 时钟图标使用 `inline-flex` + `white-space: nowrap` 绑定文本，移动端不再拆行。

**构建与发布**

- 部署包生成流程更新：排除 Git 元数据、真实配置、缓存和日志，仅打包站点文件。

### v1.12.0 - 交互增强、PWA 支持与共享主机兼容性修复

**修复共享虚拟主机 HTTP 500**

- 修复 `.htaccess` 与 `data/.htaccess` 中裸写的 `Order`/`Deny`/`Require` 指令：这些指令未做模块判断，在未加载 `mod_access_compat` 的 Apache 2.4 主机上会导致 `Invalid command 'Order'` 并在 PHP 执行前返回 500。现统一使用 `<IfModule mod_authz_core.c>` 与 `<IfModule !mod_authz_core.c>` 双分支。
- `_create_curl_handle()` 补充 `function_exists('curl_init')` 检查，共享主机未启用 cURL 扩展时降级返回而非抛出致命错误。
- 新增 `utf8Lower()` 与 `utf8Substring()` 兼容函数，替换模板中直调的 `mb_strtolower()` 与 `mb_substr()`，缺少 mbstring 扩展时不再致命；字节截断场景会清理残缺多字节序列避免乱码。
- 首页 GitHub 增强信息（Star/Fork、平台识别、更新时间）统一包裹 `function_exists('curl_init')` 判断与 `try/catch (Throwable)`，远程请求失败时降级使用 `resources.json` 本地数据，页面保持可用。
- `/health` 端点前移至所有外部网络请求之前，探活不再被 GitHub API 请求拖慢或超时。
- `error_log` 仅在日志目录确实可写时接管，否则保留主机默认日志，避免目录不可写导致启动错误无处可查。

**新增功能**

- 版本说明 Markdown 渲染：新增零依赖的 `renderMarkdownSubset()`，支持标题、粗体、行内代码、代码块、有序/无序列表与链接。采用先全量转义再选择性反转义白名单语法的策略，链接仅允许 `http`/`https`/`mailto` 协议，其余降级为纯文本，不放宽现有 CSP。
- 资源卡片热度指标：新增 `includes/batch-stats.php`，通过 `curl_multi` 批量预取仓库 Star/Fork 与更新时间；成功缓存 12 小时，API 限额耗尽时缓存 5 分钟以便限额恢复后及时刷新。超过 30 天未更新的仓库显示「长期未更新」标签。
- 下载链接一键复制：新增 `assets/copy-link.js`，采用事件委托适配异步渲染，优先使用 `navigator.clipboard`，非安全上下文回退 `execCommand`。
- 筛选结果计数：平台与分类筛选按钮显示匹配数量，随搜索关键词实时联动。
- 键盘快捷键：`Ctrl+K`/`Cmd+K` 始终可聚焦搜索框，`/` 在非输入上下文生效。
- PWA 支持：新增 `manifest.webmanifest`、`sw.js` 与 `assets/pwa.js`。静态资源采用 cache-first，页面采用 network-first 并在离线时回退缓存；详情片段接口与 `/health` 不缓存以避免版本信息过期。CSP 相应补充 `worker-src 'self'` 与 `manifest-src 'self'`。
- 移动端修复文件名截断：窄屏下下载项文件名改为换行显示，不再被省略号截断。

**构建与发布**

- Release workflow 补充打包 `manifest.webmanifest`、`sw.js` 与空的 `data/cache/` 目录；凭据校验规则调整为仅拦截缓存文件，允许空目录占位。

### v1.11.0 - 仓库分类与自定义下载

- 新增仓库分类展示：首页资源卡片按分类（阅读/工具）分组渲染，管理员通过 `resources.json` 的顶层 `categories` 字段和资源项 `category` 字段自由配置。
- 新增分类筛选按钮组：支持分类 + 平台 + 搜索三重筛选叠加，分组标题根据筛选结果自动显隐。
- 新增自定义仓库下载入口：首页顶部增加输入框，支持 `owner/repo`、`https://github.com/owner/repo` 等多种格式，自动包含预发布版本。
- 自定义仓库复用现有侧栏加载流程：异步获取 release 列表、渲染加速下载按钮、同步浏览器历史，与预配置资源体验一致。
- 增强输入校验：owner/repo 字符集限定 `[A-Za-z0-9._-]`，单段长度 1-100 字符，总长度 500 字符以内，非法输入展示格式错误提示 2.5 秒自动清除。
- 配置数据模型扩展：`sanitizeCategories()` 清洗分类数组（限长 40 字符、去重保序、最多 20 项），未配置 `categories` 时从资源 `category` 字段动态汇总。
- 更新 README 文档：补充 `categories` 与 `category` 字段说明、自定义下载使用指南、配置示例。

### v1.10.0 - 单页交互重构与视觉打磨

- 重构为单页交互：首页卡片就地展开详情侧栏，异步加载详情片段，支持深链、浏览器前进后退、Esc 关闭与焦点锁定。
- 详情内容抽取为共享片段 `templates/detail-fragment.php`，侧栏与独立详情页复用同一份标记。
- 样式表整体重写，建立设计令牌体系与明暗两套色板，移除 Bootstrap 依赖（减少 316KB 静态资源）。
- 推荐卡片改用绿色系统一强调：顶部色条、边框、实心角标，与预发布橙、正式版蓝形成层级区分。
- 详情页统计与仓库入口合并为同一行，Stars/Forks 数值拆分为主次层级。
- 下载按钮升级为主色调胶囊，热区提升至 44px，hover 时填充强调色。
- 版本列表折叠箭头改为圆形底座配旋转动画，最新版本添加绿色边框高亮。
- 站点图标替换，按 1.38:1 实际比例输出并提供 2x 高分屏资源。
- 移动端补充触摸按压反馈，适配 `prefers-reduced-motion` 动效偏好。
- 修复 `.github/workflows/release.yml` YAML 结构残缺导致的解析失败。
- `data/config.local.json` 移出版本控制，避免本地凭据配置误提交。

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
