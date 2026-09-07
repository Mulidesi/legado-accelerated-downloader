# PHP 8.2 VPS 部署说明

适合 Debian / Ubuntu VPS。站点是单入口 PHP，Web 根目录就是解压目录。

## 1. 服务器软件

```bash
# 安装 Nginx、PHP 8.2-FPM 和必需扩展
sudo apt-get update
sudo apt-get install -y nginx php8.2-fpm php8.2-cli php8.2-curl php8.2-mbstring php8.2-xml php8.2-zip ca-certificates unzip
```

Apache 方案：

```bash
sudo apt-get install -y apache2 libapache2-mod-php8.2 php8.2 php8.2-curl php8.2-mbstring php8.2-xml php8.2-zip ca-certificates unzip
sudo a2enmod rewrite headers
```

## 2. 上传并解压

把 `legado-deploy-php82.zip` 传到服务器后：

```bash
sudo mkdir -p /var/www/legado
sudo unzip -o legado-deploy-php82.zip -d /var/www/legado
sudo bash /var/www/legado/deploy/install.sh /var/www/legado www-data
```

`install.sh` 会创建 `data/cache/`、复制 `config.local.json` 模板并设置权限。

## 3. GitHub Token

编辑 `/var/www/legado/data/config.local.json`，填入 Token。也可以在 PHP-FPM pool 里设置 `env[GITHUB_TOKEN]`。

## 4. Web 服务器

Nginx：复制 `deploy/nginx.conf`，把 `YOUR_DOMAIN` 和站点路径改成实际值，启用站点后执行 `nginx -t` 并 reload。

Apache：复制 `deploy/apache-vhost.conf`，同样替换域名和路径，`AllowOverride All` 以启用项目 `.htaccess`。

PHP-FPM 可选合并 `deploy/php8.2-fpm-pool.conf`，然后：

```bash
sudo systemctl restart php8.2-fpm
sudo systemctl reload nginx
```

## 5. 验收

```bash
# 探活，cache_writable 应为 true
curl -s http://YOUR_DOMAIN/health

# 首页
curl -I http://YOUR_DOMAIN/
```

## 权限与目录

| 路径 | 权限 | 说明 |
|------|------|------|
| 站点文件 | 644 / 755 | Web 用户可读 |
| `data/` `includes/` | 750 | 禁止目录浏览 |
| `data/cache/` | 750 可写 | PHP 运行时缓存 |
| `data/config.local.json` | 640 | Token，勿公开 |

国内 VPS 直连 `api.github.com` 常被 403 拦截。站点会先探测直连，失败后自动改走 `proxyUrls` 加速节点拉取 Release。

详情弹窗仍提示「获取 release 失败」时：

```bash
# 清空 API 路由缓存后重试
sudo rm -f /var/www/legado/data/cache/*.json
curl -s "https://YOUR_DOMAIN/index.php?owner=Rimchars&repo=legado&fragment=1" | grep -E "release|获取"
```

### SSL 证书验证失败

错误信息类似 `error setting certificate verify locations: CAfile: /etc/pki/tls/certs/ca-bundle.crt CApath: none`：

```bash
# 检查系统 CA bundle 路径
php -r "echo getcwd() . '/includes/functions.php' . PHP_EOL;"
# 常见路径：
ls /etc/ssl/certs/ca-certificates.crt   # Debian/Ubuntu
ls /etc/pki/tls/certs/ca-bundle.crt     # RHEL/CentOS

# 重启 PHP-FPM 使 .user.ini 中的 curl.cainfo 生效
sudo systemctl restart php8.2-fpm
```
