# GitHub 资源加速下载站点 - 项目文档

## 项目概述

GitHub 资源加速下载站点是一个基于 PHP 的单文件 Web 应用，旨在为中国大陆地区的用户提供 GitHub Release 资源的加速下载服务。项目采用轻量级架构设计，无需数据库，使用 JSON 文件存储配置，适合部署在免费虚拟主机上。

## 核心功能

- **资源配置管理**: 通过 JSON 文件管理需要加速的 GitHub 资源
- **GitHub API 集成**: 自动获取仓库的最新 release 版本信息
- **加速下载**: 通过第三方加速代理提供下载链接
- **预发布版本支持**: 可选择是否包含预发布版本
- **响应式设计**: Bootstrap 5.3 框架，支持移动端访问

## 项目结构

```
github-accel-downloader/
├── index.php              # 主入口文件（路由 + 核心逻辑）
├── data/
│   └── resources.json     # 资源配置文件
├── templates/
│   ├── home.php           # 首页模板（资源列表）
│   └── resource.php       # 资源详情页模板
├── .monkeycode/
│   └── docs/              # 项目文档
└── README.md              # 项目说明
```

## 技术栈

| 组件 | 技术 | 版本 |
|------|------|------|
| 后端语言 | PHP | 7.4+ |
| CSS 框架 | Bootstrap | 5.3.x |
| HTTP 客户端 | cURL | - |
| 数据格式 | JSON | - |

## 快速开始

1. 上传所有文件到虚拟主机
2. 编辑 `data/resources.json` 添加资源配置
3. 访问 `index.php` 即可使用

详细使用说明请参考 README.md。

## 文档导航

- [系统架构](./ARCHITECTURE.md) - 系统设计和组件说明
- [接口文档](./INTERFACES.md) - API 和数据结构定义
- [开发者指南](./DEVELOPER_GUIDE.md) - 开发和部署指南
