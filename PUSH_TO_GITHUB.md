# 推送代码到 GitHub - 快速指南

## 方案 1：直接在 GitHub 创建并推送（推荐）

### 步骤

1. **访问 GitHub 创建仓库**
   ```
   https://github.com/new
   ```

2. **填写仓库信息**
   - Repository name: `legado-accelerated-downloader`
   - Description: `Legado 资源加速下载站点 - 基于 PHP 的 GitHub Release 加速下载`
   - **选择 Public (公开)**
   - ❌ 不要勾选 "Add a README file"
   - ❌ 不要勾选 "Add .gitignore"
   - ❌ 不要选择许可证
   - 点击 **Create repository**

3. **复制显示的命令并执行**

   创建后，GitHub 会显示推送命令，复制并执行：

   ```bash
   cd /workspace/github-accel-downloader
   git remote add origin https://github.com/YOUR_USERNAME/legado-accelerated-downloader.git
   git branch -M main
   git push -u origin main
   ```

   **注意**: 将 `YOUR_USERNAME` 替换为你的 GitHub 用户名

4. **创建 Release 标签（触发自动打包）**
   ```bash
   git tag v1.0.0
   git push origin v1.0.0
   ```

---

## 方案 2：使用本地脚本

```bash
# 1. 在 GitHub 创建仓库后
# 2. 修改下面的 YOUR_USERNAME 为你的用户名
# 3. 执行脚本

cd /workspace/github-accel-downloader

# 添加远程仓库（替换 YOUR_USERNAME）
git remote add origin https://github.com/YOUR_USERNAME/legado-accelerated-downloader.git

# 推送
git branch -M main
git push -u origin main

# 创建 Release
git tag v1.0.0
git push origin v1.0.0
```

---

## 执行后的效果

### ✓ 代码已推送
访问：`https://github.com/YOUR_USERNAME/legado-accelerated-downloader`

### ✓ Actions 自动运行
- 查看 Actions 标签页
- 等待 "Build and Release" 工作流完成
- 会自动创建 Release 并上传 ZIP 包

### ✓ Release 已创建
访问：`https://github.com/YOUR_USERNAME/legado-accelerated-downloader/releases`
- 下载 `legado-accelerated-downloader.zip`
- 包含完整的项目文件

---

## 当前本地状态

```bash
# 查看当前 commits
cd /workspace/github-accel-downloader
git log --oneline
```

**输出**:
```
4f6a2d0 chore: Add GitHub push script
bbc7f99 docs: Add deployment guide and update gitignore
a677082 feat: Initial release - Legado 资源加速下载站点
```

共 **3 个 commits**，已准备就绪！

---

## 立即执行

复制下面的命令，替换 `YOUR_USERNAME` 后执行：

```bash
cd /workspace/github-accel-downloader

# 1. 设置远程仓库（替换 YOUR_USERNAME）
git remote add origin https://github.com/YOUR_USERNAME/legado-accelerated-downloader.git

# 2. 推送代码
git branch -M main
git push -u origin main

# 3. 创建并推送标签（触发自动发布）
git tag v1.0.0
git push origin v1.0.0
```

---

## 验证

推送完成后检查：

1. **代码**: https://github.com/YOUR_USERNAME/legado-accelerated-downloader
2. **Actions**: 查看自动构建状态
3. **Releases**: 下载打包好的 ZIP 文件
