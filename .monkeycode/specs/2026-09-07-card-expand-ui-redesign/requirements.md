# Requirements Document — Homepage Card Inline Release UI

## Introduction

当前首页资源卡片仅展示仓库元信息（名称、描述、Star/Fork、平台标签、Release 时间），点击查看版本和下载需点击进入详情页或打开侧栏（Sheet）。本次重构将最近一次 Release 信息及下载入口直接内嵌到首页卡片，通过展开/收起交互提供完整版本列表，消除额外页面跳转。

## Glossary

- **Resource Card**: 首页列表中展示单个资源的基本信息卡片
- **Latest Release**: 每个仓库按语义化排序取到的第一个（非 prerelease）Release
- **Expand/Collapse**: 卡片内容的双向展开与收起交互
- **Sheet**: 当前移动端详情侧栏组件（重构后保留，作为 PC 端补充或 fallback）

## Requirements

### Requirement 1 — 首页卡片内嵌最新 Release 信息

**User Story:** AS 一名访问首页的用户，I want 直接在资源卡片上查看最新版本号和下载入口，so that 我不需要点击跳转即可快速下载最新版。

#### Acceptance Criteria

1. WHEN 首页加载完成，EACH resource card SHALL 展示该仓库最近一次非 prerelease Release 的 tag 名称
2. WHEN 存在有效下载资源（assets），EACH card SHALL 展示一个主下载按钮，链接指向最近一次 Release 的第一个文件
3. IF latest release 的 tag 与 release name 不同，card 应同时展示两者，tag 为主标识，name 为副标题
4. IF `usePrerelease` 为 true 且无稳定版，THEN card 展示最新 prerelease 信息，并在 tag chip 旁标注「预发布」

### Requirement 2 — 卡片展开/收起交互

**User Story:** AS 一名需要查看所有历史版本的用户，I want 点击卡片可以展开完整 Release 列表和全部下载链接，so that 我可以在首页完成所有操作而无需进入详情页。

#### Acceptance Criteria

1. WHEN 用户点击卡片展开区域，EACH card SHALL 展开显示该仓库全部 Release 列表（含所有 assets）
2. WHEN 卡片处于展开状态，再次点击收起按钮或点击空白遮罩，EACH card SHALL 收起回仅显示最近一次 Release 的摘要
3. WHILE card 处于展开状态，页面其余卡片应保持原位不动，不触发滚动偏移
4. IF 页面有超过一个卡片同时处于展开状态，THEN 后展开的卡片 SHALL 自动收起先展开的卡片（手风琴模式）

### Requirement 3 — 数据加载与降级策略

**User Story:** AS 一名网络受限环境下的用户，I want 即使 GitHub API 返回失败，卡片也能展示基本信息，so that 站点依然可用。

#### Acceptance Criteria

1. WHEN GitHub API 获取 Release 数据失败，EACH card SHALL 仍展示静态快照中的基本信息（名称、描述、Star/Fork）
2. IF API 成功获取 Release 数据但解析失败，THEN 展开区域显示错误提示而非空白
3. WHEN 展开请求失败，EACH card 展开区域 SHALL 显示「加载版本列表失败，请重试」并提供重试按钮

### Requirement 4 — 向后兼容

**User Story:** AS 一名通过 direct URL 访问详情页的用户，I want 原有链接依然正常工作，so that 书签和历史链接不会失效。

#### Acceptance Criteria

1. WHEN 用户访问 `index.php?owner=X&repo=Y`（无 fragment 参数），THEN 页面 SHALL 渲染完整详情页，行为与重构前完全一致
2. WHEN 用户访问 `index.php?owner=X&repo=Y&fragment=1`，THEN Sheet 加载逻辑 SHALL 保持不变
3. IF 用户通过 Search 功能找到资源，THEN 搜索结果中高亮的关键词在新卡片布局下依然可见
