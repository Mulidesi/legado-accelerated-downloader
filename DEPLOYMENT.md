# GitHub 仓库部署指南

## 快速部署步骤

### 1. 创建 GitHub 仓库

访问 GitHub 并创建新仓库：

1. 打开 https://github.com/new
2. 仓库名称：`legado-accelerated-downloader`
3. 仓库描述：Legado 资源加速下载站点 - 基于 PHP 的 GitHub Release 加速下载
4. 选择 **Public**（公开）
5. **不要** 初始化 README、.gitignore 或许可证
6. 点击 "Create repository"

### 2. 推送代码到 GitHub

在终端执行以下命令：

```bash
# 进入项目目录
cd /workspace/github-accel-downloader

# 添加远程仓库（将 YOUR_USERNAME 替换为你的 GitHub 用户名）
git remote add origin https://github.com/YOUR_USERNAME/legado-accelerated-downloader.git

# 推送到 GitHub
git branch -M main
git push -u origin main
```

### 3. 创建 GitHub Release（手动发布）

```bash
# 创建版本标签
git tag v1.0.0

# 推送标签（触发自动发布工作流）
git push origin v1.0.0
```

推送标签后，GitHub Actions 会自动：
- 打包项目文件
- 创建 Release
- 上传 ZIP 包

### 4. 启用 GitHub Pages（可选）

如果想在 GitHub Pages 上预览：

1. 进入仓库 **Settings** → **Pages**
2. Source 选择 **GitHub Actions**
3. 等待部署完成
4. 访问生成的 URL

## 自动发布工作流说明

### 触发条件

当推送以 `v` 开头的标签时自动发布，例如：
- `v1.0.0`
- `v1.0.1`
- `v1.1.0`

### 发布流程

1. **Checkout**: 拉取代码
2. **Create ZIP**: 打包项目文件
3. **Create Release**: 创建 GitHub Release
4. **Upload Assets**: 上传 ZIP 包

### 发布的内容

每次发布会自动生成：
- Release 页面（包含更新说明）
- `legado-accelerated-downloader.zip` 安装包

## 后续版本发布

发布新版本时：

```bash
# 修改版本号（ semantic versioning）
git tag v1.0.1  # Bug 修复
git tag v1.1.0  # 新功能
git tag v2.0.0  # 重大更新

# 推送标签
git push origin v1.0.1
```

## 注意事项

1. **敏感文件**: `.gitignore` 已配置忽略：
   - `data/downloads.json`（下载统计，包含用户数据）
   - `*.log`（日志文件）

2. **资源配置**: 发布包不包含 `downloads.json`，用户首次使用时会自动创建

3. **GitHub API 限制**: 未认证的 GitHub API 有 60 次/小时限制

## 验证部署

推送后检查：

1. **代码**: https://github.com/YOUR_USERNAME/legado-accelerated-downloader
2. **Actions**: 查看自动发布工作流状态
3. **Releases**: 查看生成的 Release 和 ZIP 包
4. **Pages**（如启用）: 访问部署的站点

---

**仓库地址**: https://github.com/YOUR_USERNAME/legado-accelerated-downloader
