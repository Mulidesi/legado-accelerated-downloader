# Legado 资源加速下载站点

基于 PHP 的 GitHub 资源加速下载站点，支持多平台检测和第三方加速代理，运行在免费虚拟主机环境。

[![Version](https://img.shields.io/badge/version-1.7.3-blue.svg)](https://github.com)
[![PHP](https://img.shields.io/badge/PHP-7.4+-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

## 功能特性

- 资源配置管理（JSON 文件格式）
- 调用 GitHub API 获取最新 release 版本
- 支持预发布版本筛选
- 多平台自动检测（Android、iOS、Windows、HarmonyOS、macOS、Linux）
- 平台筛选功能
- 第三方加速代理下载
- 响应式设计，支持移动端访问
- Material Design 3 风格 UI
- 明暗主题切换
- 文件缓存系统，减少 API 请求
- 并发 API 请求，提升加载速度
- 安全增强：支持环境变量和本地配置文件存储 GitHub Token

## 环境要求

- PHP 7.4+
- cURL 扩展
- JSON 扩展
- 允许访问外部 URL（GitHub API 和加速代理服务）

## 部署步骤

1. 上传所有文件到虚拟主机
2. 编辑 `data/resources.json` 添加资源配置
3. 配置 GitHub Token（可选，但推荐）
4. 访问 `index.php` 即可使用

## 配置说明

### 1. 资源配置

编辑 `data/resources.json` 文件：

```json
{
    "proxyUrls": [
        "https://ghproxy.net/",
        "https://ghproxy.monkeyray.net/",
        "https://gproxy.mlds.dpdns.org/"
    ],
    "resources": [
        {
            "name": "资源名称",
            "owner": "GitHub 用户名",
            "repo": "仓库名称",
            "description": "资源简介",
            "usePrerelease": false,
            "recommended": false
        }
    ]
}
```

### 配置字段说明

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| proxyUrls | array | 是 | 加速代理地址列表，支持轮询选择 |
| name | string | 是 | 资源显示名称 |
| owner | string | 是 | GitHub 仓库拥有者用户名 |
| repo | string | 是 | GitHub 仓库名称 |
| description | string | 是 | 资源简介描述 |
| usePrerelease | boolean | 是 | 是否包含预发布版本 |
| recommended | boolean | 否 | 是否推荐（标记后在首页置顶显示） |

### 2. GitHub Token 配置（可选）

配置 Token 后可提高 GitHub API 请求限制（60 次/小时 → 5000 次/小时）。

**方法一：环境变量（推荐）**

在虚拟主机控制面板设置环境变量 `GITHUB_TOKEN`，或在 `.htaccess` 中添加：

```apache
SetEnv GITHUB_TOKEN ghp_xxxxxxxxxxxxxxxxxxxx
```

**方法二：本地配置文件**

创建 `data/config.local.json` 文件（已包含在 `.gitignore` 中）：

```json
{
    "githubToken": "ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
}
```

> 详细迁移指南请参阅 [SECURITY_MIGRATION.md](SECURITY_MIGRATION.md)

## 可用的加速代理服务

- https://ghproxy.net/
- https://ghproxy.monkeyray.net/
- https://gproxy.mlds.dpdns.org/
- https://ghproxy.com/
- https://github.moeyy.xyz/

> 注意：加速代理服务的可用性可能随时间变化，请选择稳定的服务

## 项目结构

```
github-accel-downloader/
├── index.php              # 主入口文件
├── includes/
│   ├── config.php         # 配置加载（支持环境变量和本地配置）
│   ├── functions.php      # 公共函数库（API 调用、平台检测等）
│   └── cache.php          # 缓存系统（文件缓存 + 内存缓存）
├── data/
│   ├── resources.json     # 资源配置
│   ├── config.local.json  # 本地配置（敏感信息，不提交到 Git）
│   └── cache/             # 缓存目录
├── templates/
│   ├── home.php           # 首页模板（资源列表、平台筛选）
│   └── resource.php       # 资源详情页模板（版本列表、加速下载）
├── assets/
│   ├── bootstrap.min.css  # Bootstrap 5.3 样式
│   ├── bootstrap.bundle.min.js  # Bootstrap JS
│   ├── material-theme.css # Material Design 3 主题样式
│   ├── shared.css         # 共享样式
│   ├── theme-switcher.js  # 明暗主题切换
│   ├── favicon.ico        # 网站图标
│   └── github-icon.png    # GitHub 图标
├── SECURITY_MIGRATION.md  # 安全迁移指南
└── README.md              # 项目说明
```

## 使用说明

### 配置资源

在 `data/resources.json` 中添加要加速的 GitHub 资源。完整示例：

```json
{
    "proxyUrls": [
        "https://ghproxy.net/",
        "https://ghproxy.monkeyray.net/"
    ],
    "resources": [
        {
            "name": "阅读 Archive",
            "owner": "Rimchars",
            "repo": "legado",
            "description": "阅读 Archive 继承自 Lyc 维护的 Legado 分支，继续增强阅读体验。",
            "usePrerelease": true,
            "recommended": true
        },
        {
            "name": "阅读 Tauri",
            "owner": "LegadoTeam",
            "repo": "Legado-Tauri-Release",
            "description": "跨平台桌面阅读应用 — 基于 Tauri v2 + Vue 3 + Rust 构建",
            "usePrerelease": true
        }
    ]
}
```

### 访问站点

1. **访问首页**：查看资源配置列表，支持按平台筛选（全部、Android、iOS、HarmonyOS、Windows）
2. **点击资源卡片**：查看该资源的最近 3 个 release 版本、Stars、Forks 信息
3. **点击下载按钮**：使用配置的代理地址加速下载资源

### 平台检测

系统会自动根据文件名检测支持的发布平台：

| 平台 | 检测关键词 |
|------|-----------|
| Android | `.apk`, `arm64`, `armeabi`, `android` |
| iOS | `.ipa`, `ios` |
| Windows | `win`, `.exe`, `.msi` |
| HarmonyOS | `harmony`, `hms` |
| macOS | `mac`, `.dmg` |
| Linux | `linux`, `.deb`, `.rpm` |

## API 限制

**未配置 Token：**
- 60 次/小时（公共 API）
- 超出限制后会显示友好提示，等待一小时后自动恢复

**配置 Token 后：**
- 5000 次/小时（认证 API）
- 建议使用细粒度 Token，仅授予 Public repositories 读取权限

## 缓存机制

系统采用两级缓存机制：

1. **文件缓存**：缓存 GitHub API 响应，默认 TTL 为 1 小时
2. **内存缓存**：当前请求周期内的缓存，减少文件 I/O

缓存文件存储在 `data/cache/` 目录，可手动清空。

## 安全特性

### v1.7.3 安全增强

- GitHub Token 从环境变量或本地配置读取
- `resources.json` 不再包含敏感信息
- 添加 `.gitignore` 防止敏感文件提交
- 支持多种 Token 存储方式（环境变量 > 本地配置 > 兼容性读取）

### 文件权限建议

```bash
chmod 600 data/config.local.json
chmod 600 data/resources.json
chmod 755 data/
```

### Web 服务器防护

**Nginx：**
```nginx
location ~ /data/ {
    deny all;
    return 403;
}
```

**Apache：** 已通过 `.htaccess` 禁止访问 data/ 目录

## 常见问题

### Token 不生效

1. 检查环境变量是否正确设置
2. 确认 `data/config.local.json` 是否为有效 JSON
3. 检查 Token 格式：`ghp_` 开头，40 位字符

### 500 错误

1. 检查 JSON 配置文件格式
2. 确认文件权限可读
3. 查看错误或创建 `data/error.log` 获取详细错误

### 缓存问题

如需强制刷新缓存，可删除 `data/cache/` 目录下的所有文件。

## 注意事项

1. 免费虚拟主机可能限制外部网络连接，请确保主机允许访问 GitHub API
2. 加速代理服务的稳定性和速度因服务而异
3. 请遵守 GitHub 的服务条款和合理使用原则
4. 本项目仅为聚合下载页面，应用版权归原作者所有
5. 本项目仅供学习和技术研究使用

## 更新日志

### v1.7.3 - 安全增强版
- 支持环境变量存储 GitHub Token
- 支持本地配置文件 `config.local.json`
- 添加 `.gitignore` 保护敏感信息
- 兼容旧配置方式并提示迁移

### v1.7.x
- 添加多平台检测和筛选功能
- 优化平台检测逻辑
- 添加推荐资源置顶功能
- 并发获取资源平台信息

### v1.6.x
- 添加明暗主题切换功能
- 升级 Material Design 3 风格 UI
- 优化缓存机制

## 许可证

MIT License

## 鸣谢

本项目仅为聚合下载页面，应用版权归原作者所有。感谢所有开源贡献者！
