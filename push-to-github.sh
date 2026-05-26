#!/bin/bash

# GitHub 仓库创建和推送脚本
# 使用方法：./push-to-github.sh YOUR_USERNAME

if [ -z "$1" ]; then
    echo "错误：请提供 GitHub 用户名"
    echo "使用方法：$0 YOUR_USERNAME"
    exit 1
fi

USERNAME=$1
REPO_NAME="legado-accelerated-downloader"
REMOTE_URL="https://github.com/${USERNAME}/${REPO_NAME}.git"

echo "======================================"
echo "Legado 资源加速下载 - GitHub 推送脚本"
echo "======================================"
echo ""
echo "目标仓库：${REPO_NAME}"
echo "仓库地址：${REMOTE_URL}"
echo ""

# 检查 Git 是否已配置
if ! git config user.email > /dev/null 2>&1; then
    echo "正在配置 Git 用户信息..."
    git config user.email "github-actions@github.com"
    git config user.name "GitHub Actions"
fi

# 检查是否已有 remote
if git remote -v | grep -q origin; then
    echo "更新 remote origin..."
    git remote set-url origin "${REMOTE_URL}"
else
    echo "添加 remote origin..."
    git remote add origin "${REMOTE_URL}"
fi

# 重命名分支为 main
echo "重命名分支为 main..."
git branch -M main

# 显示推送命令
echo ""
echo "======================================"
echo "执行以下命令推送代码到 GitHub:"
echo "======================================"
echo ""
echo "git push -u origin main"
echo ""
echo "创建 Release 标签:"
echo "git tag v1.0.0"
echo "git push origin v1.0.0"
echo ""
echo "======================================"
echo "或者手动执行："
echo "1. 在 GitHub 创建仓库：${USERNAME}/${REPO_NAME}"
echo "2. 运行：git push -u origin main"
echo "3. 运行：git tag v1.0.0 && git push origin v1.0.0"
echo "======================================"
