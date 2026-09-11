# Technical Design — Homepage Card Inline Release UI

Feature Name: 2026-09-07-card-expand-ui-redesign
Updated: 2026-09-07

## Description

将首页资源卡片的版本展示与下载入口从独立详情页/Sheet 迁移到卡片内部，通过展开/收起交互控制内容层级。核心变更：

1. **后端**：新增 `getResourceLatestReleaseWithAssetsBatch()` 函数，批量获取各资源最新 Release（含 asset 列表）
2. **模板**：重写 `home.php` 中卡片结构，支持两态展示（折叠态/展开态）
3. **JS**：重写 `app.js` 卡片点击逻辑，由 Sheet 跳转改为内联展开/收起
4. **CSS**：新增展开态动画与布局样式

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                       index.php                             │
│  ┌───────────────────────────────────────────────────────┐  │
│  │  normalizeResourceCardData()                          │  │
│  │  ───────────────────────────────────────────────────  │  │
│  │  + getResourceLatestReleaseWithAssetsBatch() [新增]   │  │
│  │  注入 $resources[i]['latestRelease'] = {              │  │
│  │    tag_name, name, published_at, assets: [...]        │  │
│  │  }                                                    │  │
│  └───────────────────────────────────────────────────────┘  │
│                          ↓                                   │
│  ┌───────────────────────────────────────────────────────┐  │
│  │  templates/home.php                                   │  │
│  │  ───────────────────────────────────────────────────  │  │
│  │  卡片折叠态：release tag + 主下载按钮 + 「查看全部 v」  │  │
│  │  卡片展开态（.card.expanded）：                        │  │
│  │    ├─ 最近版本（高亮）                                │  │
│  │    ├─ 历史版本列表（details/summary）                 │  │
│  │    └─ 各版本 assets 下载行                            │  │
│  └───────────────────────────────────────────────────────┘  │
│                          ↓                                   │
│  ┌───────────────────────────────────────────────────────┐  │
│  │  assets/app.js                                        │  │
│  │  ───────────────────────────────────────────────────  │  │
│  │  卡片点击 → toggle .expanded class                      │  │
│  │  首次展开 → 预取 full release 数据（如需）              │  │
│  │  手风琴模式 → 其他展开卡片自动关闭                      │  │
│  └───────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

## Components and Interfaces

### 1. Backend: `getResourceLatestReleaseWithAssetsBatch()`

**Location**: `includes/cache.php`（新增函数）

**Input**: `$resources` — 与 `getResourcePlatformsBatch()` 相同格式的资源数组

**Output**: `array<int, array|null>` — 与 `$resources` 索引对齐，元素为：
```php
array(
    'tag_name'    => string,     // e.g. "archive-v3-3.26.09041221"
    'name'        => string|null,// release title，可为 null
    'published_at'=> string|null,// ISO 8601 date
    'prerelease'  => bool,
    'assets'      => array<int, array>, // [{name, size, browser_download_url}]
    '_isLatest'   => bool,
)
```

**逻辑**：
- 复用 `getGitHubReleasesWithCache()` 获取各资源 releases
- 过滤稳定版优先（`prerelease=false`），若全为 prerelease 则取第一个
- 仅返回第一个 release 的数据（节省缓存空间）
- 合并代理 URL 生成下载链接

**函数签名**：
```php
function getResourceLatestReleaseWithAssetsBatch(array $resources): array
```

### 2. Template: 卡片两态结构

**Location**: `templates/home.php` lines 229–313（重写 card HTML 结构）

**折叠态（默认）**：
```html
<article class="card" data-owner="..." data-repo="...">
  <!-- 头部：标题 + badges（不变） -->
  <div class="card-top">...</div>
  <!-- 元信息：repo link + stats（不变） -->
  <div class="card-subline">...</div>
  <!-- 描述（不变） -->
  <p class="card-desc">...</p>
  <!-- chips（不变） -->
  <div class="card-chips">...</div>
  
  <!-- 新增：Release 摘要区（折叠态显示，展开态隐藏） -->
  <div class="card-release-summary">
    <span class="chip chip-mono chip-accent">v3.26.09041221</span>
    <a class="btn btn-download-sm" href="/proxy/...apk">
      <svg>↓</svg> 下载 APK
    </a>
    <button class="card-expand-btn" aria-expanded="false">
      查看全部版本 ▾
    </button>
  </div>
  
  <div class="card-spacer"></div>
  <div class="card-foot">...</div>
  
  <!-- 新增：展开内容区（默认 hidden） -->
  <div class="card-expanded-content" hidden>
    <!-- 通过 PHP 内嵌或 JS 动态加载 -->
  </div>
</article>
```

**展开态**（`$resource['latestRelease']` 已在 PHP 层预加载）：
- 展开区内嵌完整 release 详情
- 使用 `<details>` 元素做子版本折叠
- Latest 版本默认展开，历史版本默认折叠

### 3. JavaScript: 卡片交互

**Location**: `assets/app.js`（重写卡片点击处理，lines 340+）

**现有逻辑**（待替换）：
```js
card.addEventListener('click', function(e) {
  // 打开 Sheet，fetch fragment 页面
});
```

**新逻辑**：
```js
// 单例：当前展开的卡片
let expandedCard = null;

card.addEventListener('click', function(e) {
  // 忽略下载链接和复制按钮上的点击
  if (e.target.closest('.asset') || e.target.closest('.asset-copy')) return;
  
  const isExpandBtn = e.target.closest('.card-expand-btn');
  const isSummaryArea = e.target.closest('.card-release-summary');
  
  if (isExpandBtn || isSummaryArea) {
    e.preventDefault();
    toggleCardExpand(card);
  }
});

function toggleCardExpand(card) {
  const isExpanded = card.classList.contains('expanded');
  
  // 手风琴：关闭其他展开卡片
  if (expandedCard && expandedCard !== card) {
    collapseCard(expandedCard);
  }
  
  if (isExpanded) {
    collapseCard(card);
  } else {
    expandCard(card);
  }
}
```

### 4. CSS: 展开动画与布局

**Location**: `assets/material-theme.css`（新增样式段）

新增 CSS 类：
```css
/* 展开按钮 */
.card-expand-btn { ... }
.card-expand-btn[aria-expanded="true"] { transform: rotate(180deg); }

/* 展开内容区 */
.card-expanded-content {
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.3s ease, opacity 0.2s ease;
  opacity: 0;
}
.card.expanded .card-expanded-content {
  max-height: 2000px; /* 足够大的值配合 JS 精确控制 */
  opacity: 1;
}

/* 展开态摘要区隐藏 */
.card.expanded .card-release-summary { display: none; }

/* 手风琴容器 */
.card.expanded { border-color: var(--accent); }
```

## Data Models

### 新增字段注入路径

```
index.php (lines 240-257)
  ↓ 在 platform/releaseUpdatedAt 赋值之后
  ↓ 新增调用
getResourceLatestReleaseWithAssetsBatch($resources)
  ↓
foreach ($resources as $index => $resource) {
    $resources[$index]['latestRelease'] = $latestReleases[$index];
}
  ↓
templates/home.php (card rendering loop)
  ↓ 读取 $resource['latestRelease']
```

### 数据结构

```php
// $resource['latestRelease']
array(
    'tag_name'    => 'archive-v3-3.26.09041221',
    'name'        => null,               // 可选，release title
    'published_at'=> '2026-09-04T12:21:00Z',
    'prerelease'  => false,
    'assets'      => [
        ['name' => 'legado-arm64-v8a-release.apk', 'size' => 45000000, 'url' => '/proxy/...'],
        ['name' => 'legado-armeabi-v7a-release.apk', 'size' => 32000000, 'url' => '/proxy/...'],
    ],
    '_isLatest'   => true,
)
```

## Correctness Properties

1. **不变量**：无论 GitHub API 是否可用，卡片折叠态始终渲染（依赖 `normalizeResourceCardData()` 静态快照）
2. **不变量**：Sheet 详情页面（`fragment=1`）行为不受影响，原有 direct URL 访问路径保留
3. **约束**：展开态仅在客户端渲染，服务端不返回展开内容（避免首屏负载过高）
4. **约束**：同一时刻最多一张卡片处于展开态（手风琴模式）

## Error Handling

| 场景 | 处理方式 |
|------|----------|
| `latestRelease` 为 null（API 失败或无 release） | 折叠态不渲染 release 摘要区，卡片降级为纯信息展示 |
| Release assets 为空数组 | 折叠态显示「暂无下载资源」提示 |
| 展开时 JS 预取失败 | 显示 error state 卡片内错误提示块 |
| `buildAcceleratedUrlOptimized()` 无代理配置 | 回退到原始 `browser_download_url` |

## Test Strategy

1. **单元测试**：`getResourceLatestReleaseWithAssetsBatch()` 输入 mock resources，验证返回值结构与缓存命中逻辑
2. **集成测试**：部署后访问首页，验证：
   - 无 Release 数据时卡片正常渲染
   - 有 Release 数据时 tag 和下载按钮正确显示
   - 点击展开/收起动画流畅
   - 手风琴模式符合预期
3. **回归测试**：
   - `?owner=X&repo=Y` 详情页访问正常
   - `?owner=X&repo=Y&fragment=1` Sheet 加载正常
   - 搜索过滤后卡片交互正常

## Implementation Phases

| 阶段 | 文件 | 工作内容 | 预计工作量 |
|------|------|----------|-----------|
| P1 | `includes/cache.php` | 新增 `getResourceLatestReleaseWithAssetsBatch()` | 30 min |
| P2 | `index.php` | 在资源增强循环中注入 `latestRelease` 字段 | 10 min |
| P3 | `templates/home.php` | 重写卡片 HTML 结构（折叠态+展开态） | 60 min |
| P4 | `assets/app.js` | 重写卡片点击逻辑，实现手风琴展开/收起 | 45 min |
| P5 | `assets/material-theme.css` | 新增展开动画、按钮、布局样式 | 40 min |
| P6 | 联调测试 | 本地验证 + VPS 部署 + 功能测试 | 30 min |
| **合计** | | | **约 3.5 小时** |

## References

[^1]: (`templates/home.php:229-313`) — 当前卡片渲染循环，参考结构进行改造
[^2]: (`templates/detail-fragment.php:64-186`) — 完整 release 展示逻辑，展开态内容来源
[^3]: (`assets/app.js:23-373`) — 当前 Sheet 交互逻辑，需替换为内联展开
[^4]: (`index.php:208-263`) — 首页数据增强循环，在此插入新批次查询
