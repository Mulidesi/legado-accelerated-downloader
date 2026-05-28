# 安全迁移指南 v1.7.3

## 安全风险说明

**旧版本 (v1.7.2 及之前)**: GitHub Token 存储在 `data/resources.json` 中
- 风险 1: 可能意外提交到 Git 仓库
- 风险 2: Web 服务器配置错误时可直接访问
- 风险 3: 备份文件包含敏感信息

**新版本 (v1.7.3+)**: 支持三种安全存储方式

## 迁移步骤

### 方法 1: 环境变量（推荐）

在虚拟主机控制面板或通过 `.htaccess` 设置环境变量：

**cPanel / 虚拟主机面板:**
1. 进入 "Environment Variables" 或 "PHP Settings"
2. 添加变量：
   - Name: `GITHUB_TOKEN`
   - Value: `ghp_xxxxxxxxxxxxxxxxxxxx`

**使用 .htaccess (Apache):**
```apache
SetEnv GITHUB_TOKEN ghp_xxxxxxxxxxxxxxxxxxxx
```

**使用 .user.ini (PHP-FPM):**
```ini
env[GITHUB_TOKEN] = "ghp_xxxxxxxxxxxxxxxxxxxx"
```

**验证是否生效:**
创建一个 `test.php` 文件：
```php
<?php echo getenv('GITHUB_TOKEN') ? '已设置' : '未设置'; ?>
```

### 方法 2: 本地配置文件（简单）

1. 复制示例文件：
   ```bash
   cp data/config.local.json.example data/config.local.json
   ```

2. 编辑 `data/config.local.json`，填入你的 Token：
   ```json
   {
       "githubToken": "ghp_xxxxxxxxxxxxxxxxxxxx"
   }
   ```

3. **重要**: 确保该文件不会被提交到 Git
   ```bash
   # 已经添加 .gitignore，但请确认
   cat .gitignore | grep config.local
   ```

### 方法 3: 保留原方式（不推荐）

如果不做迁移，`data/resources.json` 中的 Token 仍然有效，但会在日志中看到警告。

建议仅在无法使用方法 1 或 2 时使用。

## 清理旧数据

迁移完成后，从 `data/resources.json` 中移除敏感信息：

1. 编辑 `data/resources.json`
2. 删除或清空 `githubToken` 字段：
   ```json
   {
       "proxyUrls": [...],
       "githubToken": "",  // 清空或删除此行
       "resources": [...]
   }
   ```

## 安全增强措施

### 1. 文件权限设置

```bash
# 设置权限，防止 Web 直接访问敏感文件
chmod 600 data/config.local.json
chmod 600 data/resources.json
chmod 755 data/
```

### 2. Web 服务器配置

**Nginx - 禁止访问敏感文件：**
```nginx
location ~ /data/ {
    deny all;
    return 403;
}
```

**Apache - 使用 .htaccess：**
已包含在项目中，禁止访问 data/ 目录。

### 3. Git 保护

确保 `.gitignore` 包含：
```
data/config.local.json
*.local.json
data/cache/
data/*.log
```

## 验证迁移成功

访问首页，检查：
1. 页面正常加载（HTTP 200）
2. 资源详情页能显示 Stars/Forks（说明 Token 有效）
3. 无安全警告日志

查看错误日志（如有问题）：
```bash
tail -f data/error.log
```

## Token 安全建议

1. **使用细粒度 Token**: GitHub Settings → Developer settings → Personal access tokens → Fine-grained tokens
2. **最小权限**: 仅授予 "Public repositories" 读取权限
3. **定期更换**: 建议每 90 天更换一次
4. **监控使用**: 在 GitHub 上查看 Token 使用记录

## 问题排查

### Token 不生效

1. 检查环境变量是否正确设置
2. 确认 PHP 可以读取环境变量：`phpinfo()` 中查看 Environment
3. 检查 Token 格式：`ghp_` 开头，40位字符

### 500 错误

1. 检查 `data/config.local.json` 是否为有效 JSON
2. 确认文件权限可读
3. 查看 `data/error.log` 获取详细错误

### 提交时仍包含 Token

1. 从 Git 历史中移除敏感信息：
   ```bash
   git filter-branch --force --index-filter \
   'git rm --cached --ignore-unmatch data/config.local.json' \
   --prune-empty --tag-name-filter cat -- --all
   ```
2. 强制推送：`git push origin --force --all`

## 联系方式

如有问题，请查看：
- 错误日志: `data/error.log`
- 配置示例: `data/config.local.json.example`
