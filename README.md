# GitHub 资源加速下载站点

基于 PHP 的 GitHub 资源加速下载站点，运行在免费虚拟主机环境。

## 功能特性

- 资源配置管理（JSON 文件格式）
- 调用 GitHub API 获取最新 release 版本
- 支持预发布版本筛选
- 第三方加速代理下载
- 响应式设计，支持移动端访问
- Bootstrap 5.3 UI 框架

## 环境要求

- PHP 7.4+
- cURL 扩展
- 允许访问外部 URL

## 部署步骤

1. 上传所有文件到虚拟主机
2. 编辑 `data/resources.json` 添加资源配置
3. 访问 `index.php` 即可使用

## 配置说明

编辑 `data/resources.json` 文件：

```json
{
    "proxyUrl": "https://ghproxy.net/",
    "resources": [
        {
            "name": "资源名称",
            "owner": "GitHub 用户名",
            "repo": "仓库名称",
            "description": "资源简介",
            "usePrerelease": false
        }
    ]
}
```

### 配置字段说明

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| proxyUrl | string | 是 | 加速代理地址，如 https://ghproxy.net/ |
| name | string | 是 | 资源显示名称 |
| owner | string | 是 | GitHub 仓库拥有者用户名 |
| repo | string | 是 | GitHub 仓库名称 |
| description | string | 是 | 资源简介描述 |
| usePrerelease | boolean | 是 | 是否包含预发布版本 |

## 可用的加速代理服务

- https://ghproxy.net/
- https://ghproxy.com/
- https://github.moeyy.xyz/

> 注意：加速代理服务的可用性可能随时间变化，请选择稳定的服务

## 项目结构

```
github-accel-downloader/
├── index.php              # 主入口文件
├── data/
│   └── resources.json     # 资源配置
├── templates/
│   ├── home.php           # 首页模板
│   └── resource.php       # 资源详情页模板
└── README.md              # 项目说明
```

## 使用说明

### 配置资源

在 `data/resources.json` 中添加要加速的 GitHub 资源。示例：

```json
{
    "proxyUrl": "https://ghproxy.net/",
    "resources": [
        {
            "name": "阅读 Max",
            "owner": "GEd520",
            "repo": "legados",
            "description": "阅读 Max 继承自阅读 Sigma，在其基础上新增更多功能。",
            "usePrerelease": true
        }
    ]
}
```

### 访问站点

1. 访问首页：查看资源配置列表
2. 点击资源卡片：查看该资源的最近 3 个 release 版本
3. 点击"加速下载"：使用配置的代理地址下载资源

## API 限制

使用未认证的 GitHub API 有速率限制：
- 60 次/小时
- 超出限制后会显示友好提示，等待一小时后自动恢复

如需更高限制，可考虑配置 GitHub Personal Access Token。

## 注意事项

1. 免费虚拟主机可能限制外部网络连接，请确保主机允许访问 GitHub API
2. 加速代理服务的稳定性和速度因服务而异
3. 请遵守 GitHub 的服务条款和合理使用原则
4. 本项目仅供学习和技术研究使用

## 许可证

MIT License
