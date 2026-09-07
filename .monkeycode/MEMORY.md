# User Instruction Memory

This file records user instructions, preferences, and teachings for reference in future interactions.

## Format

### User Instruction Entry
User instruction entries should follow this format:

[User Instruction Summary]
- Date: [YYYY-MM-DD]
- Context: [Mentioned scenario or time]
- Instructions:
  - [Content of user teaching or instruction, described line by line]

### Project Knowledge Entry
Entries discovered by the Agent during task execution should follow this format:

[Project Knowledge Summary]
- Date: [YYYY-MM-DD]
- Context: Discovered by Agent while performing [specific task description]
- Category: [Operations & Deployment|Build Methods|Testing Methods|Troubleshooting & Debugging|Workflow & Collaboration|Environment Configuration]
- Instructions:
  - [Specific knowledge points, described line by line]

## Deduplication Strategy
- Before adding a new entry, check for similar or identical instructions.
- If a duplicate is found, skip the new entry or merge it with the existing one.
- When merging, update the context or date information.
- This helps avoid redundant entries and keeps the memory file tidy.

## Entries

[Project Knowledge Summary]
- Date: 2026-09-07
- Context: 首次部署 Legado 资源加速下载站
- Category: Operations & Deployment
- Instructions:
  - VPS IP: 154.58.233.54，日本机房，路径 /xp/www/gproxy.27464828.xyz/
  - SSH 客户端不可用，需用户在 VPS 上手动执行 git pull
  - 部署命令：cd /xp/www/gproxy.27464828.xyz && git pull origin main
  - 健康检查：curl -s https://gproxy.27464828.xyz/health
  - PHP 8.2-FPM + Nginx，SSL 通过 Let's Encrypt

[Project Knowledge Summary]
- Date: 2026-09-07
- Context: CA Bundle 修复验证
- Category: Troubleshooting & Debugging
- Instructions:
  - getSystemCaBundlePath() 扫描顺序：/etc/ssl/certs/ca-certificates.crt → /etc/ssl/ca-bundle.pem → /etc/pki/tls/certs/ca-bundle.crt → /etc/ssl/cert.pem
  - JP IP 直连 GitHub API 正常（无需代理）
  - 强制 IPv4：CURL_IPRESOLVE_V4 避免 IPv6 超时
  - Bearer Token 格式：Authorization: Bearer xxx（不是 ?token=xxx 查询参数）

[Project Knowledge Summary]
- Date: 2026-09-07
- Context: OJO Design Skills 合规重构
- Category: Build Methods
- Instructions:
  - OJO Design Skills 源：https://github.com/touchine-ojo/OJO-Design-Skills
  - 禁止：深紫黑底色（#0D0B1A 等）、紫蓝主色搭配、霓虹光晕、玻璃拟态边框发光、灰块占位
  - 推荐参考产品：Linear、Stripe、Notion、Vercel（锌色中性深色 + 单一强调色）
  - 当前项目已采用方向 A：#0F0F11 锌色深色 + #3B82F6 靛蓝主色（Linear 风格）
  - 色值变更需同步更新：material-theme.css 设计令牌、theme-color meta、manifest.webmanifest theme_color
