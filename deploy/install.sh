#!/bin/bash
# VPS 安装辅助脚本（Debian/Ubuntu + Nginx + PHP 8.2-FPM）
# 用法：在解压后的站点根目录执行
#   sudo bash deploy/install.sh /var/www/legado www-data

set -euo pipefail

TARGET_DIR="${1:-/var/www/legado}"
WEB_USER="${2:-www-data}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
SOURCE_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

echo "站点目录: ${TARGET_DIR}"
echo "Web 用户: ${WEB_USER}"
echo "源目录: ${SOURCE_DIR}"

mkdir -p "${TARGET_DIR}"
cp -a "${SOURCE_DIR}/." "${TARGET_DIR}/"
mkdir -p "${TARGET_DIR}/data/cache"

if [ -f "${TARGET_DIR}/data/config.local.json.example" ] && [ ! -f "${TARGET_DIR}/data/config.local.json" ]; then
    cp "${TARGET_DIR}/data/config.local.json.example" "${TARGET_DIR}/data/config.local.json"
    echo "已生成 data/config.local.json，请填入 GitHub Token"
fi

chown -R "${WEB_USER}:${WEB_USER}" "${TARGET_DIR}"
find "${TARGET_DIR}" -type d -exec chmod 755 {} \;
find "${TARGET_DIR}" -type f -exec chmod 644 {} \;
chmod 750 "${TARGET_DIR}/data" "${TARGET_DIR}/includes" "${TARGET_DIR}/data/cache"
chmod 640 "${TARGET_DIR}/data/resources.json" || true
if [ -f "${TARGET_DIR}/data/config.local.json" ]; then
    chmod 640 "${TARGET_DIR}/data/config.local.json"
fi

echo "文件已复制。接下来："
echo "1. 编辑 ${TARGET_DIR}/data/config.local.json 填入 Token"
echo "2. 复制 deploy/nginx.conf 到 /etc/nginx/sites-available/ 并替换域名与路径"
echo "3. 启用 PHP 扩展：php8.2-curl php8.2-mbstring php8.2-json php8.2-xml"
echo "4. nginx -t && systemctl reload nginx && systemctl restart php8.2-fpm"
echo "5. 访问 /health 检查 cache_writable 是否为 true"
