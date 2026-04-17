# 缪斯 MUSE 论坛 — 维护手册

> 最后更新：2026-04-17  
> GitHub：https://github.com/lingyunalingyun/Muse-Forum

---

## 目录

1. [项目概览](#1-项目概览)
2. [目录结构](#2-目录结构)
3. [数据库表](#3-数据库表)
4. [模块说明](#4-模块说明)
5. [角色与权限](#5-角色与权限)
6. [经验与等级](#6-经验与等级)
7. [配置说明](#7-配置说明)
8. [开发工作流](#8-开发工作流)
9. [常见维护操作](#9-常见维护操作)
10. [部署注意事项](#10-部署注意事项)

---

## 1. 项目概览

| 项目 | 说明 |
|------|------|
| 站点名称 | 缪斯 MUSE |
| 主题风格 | Dark Terminal（暗色终端，游戏/科研方向） |
| 主色调 | `#3fb950`（绿）/ 背景 `#0d1117` + `#161b22` |
| 语言 | PHP 7.x + MySQL + 原生 JS |
| 编辑器 | WangEditor（富文本） |

**两个文件夹说明：**

| 文件夹 | 用途 |
|--------|------|
| `F:\Desktop\论坛开发_本地版` | 开发版，本地调试用，含真实 DB 密码 |
| `F:\Desktop\论坛开发_上传版` | 上传版，敏感信息脱敏处理后推送 GitHub |

**工作流：** 本地版修改 → 复制到上传版 → 上传版安全处理 → 推送 GitHub

---

## 2. 目录结构

```
/
├── config.php                  全局配置（DB 连接、站点常量）
├── index.php                   首页（推荐帖 + 侧栏）
├── square.php                  广场（全部帖子，分页+分区筛选）
├── categories.php              分区入口（PS5 游戏库风格卡片）
├── search.php                  全站搜索
├── topic.php                   话题页（#标签聚合）
├── style.css                   全站基础样式
│
├── includes/
│   ├── header.php              全局导航栏（粘性顶栏 + 中途封禁检测）
│   ├── exp_helper.php          核心辅助函数库（经验/等级/角色/邮件/DB 迁移）
│   └── text_format.php         文本格式化（#话题 @提及 渲染 + 话题持久化）
│
├── pages/
│   ├── login.php               登录页
│   ├── register.php            注册页
│   ├── logout.php              登出
│   ├── verify.php              邮箱验证
│   ├── forgot_password.php     忘记密码
│   ├── reset_password.php      重置密码
│   ├── profile.php             用户主页
│   ├── edit_profile.php        编辑资料
│   ├── settings.php            个人设置（隐私 + 黑名单）
│   ├── publish.php             发帖页（WangEditor）
│   ├── post.php                帖子详情页
│   ├── notices.php             社区公告列表
│   ├── notifications.php       消息中心
│   ├── admin.php               管理后台（审核 + 封禁）
│   └── admin_categories.php    分区管理
│
├── actions/                    AJAX/表单处理端点
│   ├── auth.php                认证（注册/登录/找回密码）
│   ├── save.php                发帖/保存草稿
│   ├── post_action.php         帖子点赞/收藏 toggle
│   ├── post_delete.php         删除帖子
│   ├── post_edit.php           编辑帖子（10 分钟冷却）
│   ├── post_recommend.php      推荐/取消推荐（管理员）
│   ├── comment_save.php        提交评论/回复
│   ├── comment_delete.php      删除评论（级联子回复）
│   ├── comment_like.php        评论点赞 toggle
│   ├── comment_top.php         评论置顶 toggle
│   ├── follow_toggle.php       关注/取消关注
│   ├── block_user.php          拉黑/取消拉黑
│   ├── ban_user.php            封禁/解封（管理员）
│   ├── message_send.php        发送私信
│   ├── upload_image.php        WangEditor 图片上传
│   ├── upload_attachment.php   附件上传
│   ├── user_search.php         @提及用户名搜索
│   └── user_handler.php        ⚠️ 废弃旧接口，勿调用
│
├── uploads/
│   ├── avatars/                用户头像
│   ├── posts/                  帖子内图片
│   ├── attachments/            帖子附件
│   └── categories/             分区封面图
│
└── 4xx.php / 5xx.php           自定义错误页（400/401/403/404/429/500/502/503）
```

---

## 3. 数据库表

### 主要表

| 表名 | 说明 |
|------|------|
| `users` | 用户主表 |
| `posts` | 帖子表 |
| `comments` | 评论/回复表 |
| `categories` | 分区表 |
| `follows` | 关注关系 |
| `user_blocks` | 黑名单 |
| `notifications` | 通知消息 |
| `messages` | 私信 |
| `post_likes` | 帖子点赞 |
| `post_favs` | 帖子收藏 |
| `comment_likes` | 评论点赞 |
| `topics` | 话题（#标签） |
| `post_topics` | 帖子-话题关联 |

### users 表关键字段

| 字段 | 说明 |
|------|------|
| `id` | 自增主键 |
| `userid` | 6位随机数字 ID（注册时生成，用于登录） |
| `email` | 邮箱（唯一） |
| `username` | 昵称（唯一） |
| `password` | bcrypt 哈希 |
| `role` | `owner` / `admin` / `sponsor` / `user` |
| `is_banned` | 0=正常，1=封禁 |
| `ban_reason` | 封禁原因 |
| `exp` | 当前经验值 |
| `level` | 当前等级（1-6，由 add_exp 自动更新） |
| `login_streak` | 连续登录天数 |
| `last_login_date` | 最后登录日期 |
| `email_verified` | 邮箱是否验证（0/1） |
| `verify_token` | 验证 token（24h） |
| `reset_token` | 重置密码 token（1h） |
| `show_followers` | 粉丝列表是否公开 |
| `show_following` | 关注列表是否公开 |
| `post_visibility` | 帖子可见性默认值 |

### posts 表关键字段

| 字段 | 说明 |
|------|------|
| `status` | `草稿` / `待审核` / `已发布` |
| `is_recommend` | 1=出现在首页推荐网格 |
| `is_notice` | 1=公告帖 |
| `category_id` | 所属分区（NULL=无分区） |
| `attachments` | 附件信息 JSON 字符串 |
| `edited_at` | 最后编辑时间（10 分钟冷却依据） |
| `approved_by` | 审核人 user_id |
| `approved_at` | 审核通过时间 |

---

## 4. 模块说明

### 认证流程（actions/auth.php）

```
注册 → 创建 users 记录 → EMAIL_VERIFY_REQUIRED?
       ├─ true  → 发验证邮件 → 用户点链接 → verify.php → email_verified=1
       └─ false → 直接 email_verified=1 → 跳登录页

登录 → 密码验证 → 封禁检查 → 邮箱验证检查 → 写 session → 登录经验奖励
```

### 发帖流程（actions/save.php）

```
publish.php 填写 → AJAX POST save.php
                    ├─ is_draft=1  → status='草稿'
                    ├─ is_notice=1 → status='已发布'（admin/owner 跳过审核）
                    └─ 普通帖子   → status='待审核' → 通知所有 admin
                                                      管理员在 admin.php 审核通过
```

### 通知类型速查

| type | 触发时机 |
|------|----------|
| `comment` | 有人评论你的帖子 |
| `reply` | 有人回复你的评论 |
| `mention` | 评论中 @了你 |
| `like_post` | 有人点赞你的帖子 |
| `fav_post` | 有人收藏你的帖子 |
| `like_comment` | 有人点赞你的评论 |
| `follow` | 有人关注了你 |
| `message` | 有人发私信给你 |
| `post_review` | 帖子提交审核（通知管理员） |
| `post_approved` | 帖子审核通过（通知作者） |

### 帖子可见性（post_visibility）

| 值 | 说明 |
|----|------|
| `public` | 所有人可见（默认） |
| `followers` | 仅关注了我的人可见 |
| `following` | 仅我关注的人可见 |
| `mutual` | 仅互相关注可见 |
| `private` | 仅自己可见 |

> admin / owner 绕过所有可见性限制。

---

## 5. 角色与权限

| role | 颜色 | 标签 | 可封禁 |
|------|------|------|--------|
| `owner` | `#f85149` 红 | ★ 站长 | 可封禁 admin / user |
| `admin` | `#a78bfa` 紫 | 管理员 | 只能封禁 user |
| `sponsor` | `#3fb950` 绿 | 赞助者 | — |
| `user` | `#58a6ff` 蓝 | 成员 | — |
| （封禁状态） | `#6e7681` 灰 | 已封禁 | — |

**角色徽章统一由 `get_role_badge()` / `get_role_info()` 在 `includes/exp_helper.php` 输出，不要在其他地方硬编码颜色。**

---

## 6. 经验与等级

### 等级阈值

| 等级 | 名称 | 所需总 EXP | 颜色 |
|------|------|-----------|------|
| Lv1 | 新手 | 0 | `#8b949e` 灰 |
| Lv2 | 学徒 | 1,000 | `#58a6ff` 蓝 |
| Lv3 | 成员 | 5,000 | `#3fb950` 绿 |
| Lv4 | 精英 | 15,000 | `#d29922` 金 |
| Lv5 | 大师 | 30,000 | `#f0883e` 橙 |
| Lv6 | 传奇 | 50,000 | `#f85149` 红 |

### 每日登录奖励

- 公式：`min(连续天数 × 10, 100)` EXP
- 连续第 1 天 = 10 EXP，第 10 天及以上 = 100 EXP（上限）
- 中断后连续天数重置为 1

### 相关函数（includes/exp_helper.php）

| 函数 | 说明 |
|------|------|
| `add_exp($conn, $user_id, $amount)` | 增加 EXP 并自动更新 level，返回 ['exp', 'level'] |
| `get_level_by_exp($exp)` | 根据 EXP 返回等级数字 |
| `calc_login_exp($streak)` | 根据连续天数计算当日奖励 EXP |

---

## 7. 配置说明

### config.php 常量

| 常量 | 说明 | 注意 |
|------|------|------|
| `SITE_URL` | 站点根 URL（无末尾斜杠） | 用于邮件中的链接拼接，必须与实际域名一致 |
| `SITE_NAME` | 站点名称 | 显示在邮件头部和页面标题 |
| `MAIL_FROM` | 发件人邮箱 | 需要服务器 PHP `mail()` 能正常发信 |
| `EMAIL_VERIFY_REQUIRED` | `true`=注册需验证邮箱，`false`=直接激活 | 当前为 `false` |

### 上传目录权限

以下目录需要 PHP 进程可写（权限 755 或 777）：

```
uploads/avatars/
uploads/posts/
uploads/attachments/
uploads/categories/
```

---

## 8. 开发工作流

```
1. 在 论坛开发_本地版 修改代码并本地测试

2. 确认没问题后，将修改的文件复制到 论坛开发_上传版

3. 在上传版中：
   - 替换 config.php 中的数据库账密为生产环境值
   - 检查是否有 console.log / var_dump / die() 等调试代码

4. git add → git commit → git push 到 GitHub

5. 服务器从 GitHub 拉取更新
```

---

## 9. 常见维护操作

### 手动设置某用户为管理员

```sql
UPDATE users SET role = 'admin' WHERE username = '用户名';
```

### 手动推荐/取消推荐某帖子

```sql
UPDATE posts SET is_recommend = 1 WHERE id = 帖子ID;
UPDATE posts SET is_recommend = 0 WHERE id = 帖子ID;
```

### 手动发布待审核帖子（绕过后台）

```sql
UPDATE posts SET status = '已发布', approved_at = NOW() WHERE id = 帖子ID;
```

### 手动封禁用户

```sql
UPDATE users SET is_banned = 1, ban_reason = '封禁原因' WHERE username = '用户名';
```

### 清理孤立数据（删帖后遗留的评论/点赞）

```sql
-- 清理评论（帖子已不存在）
DELETE FROM comments WHERE post_id NOT IN (SELECT id FROM posts);
-- 清理点赞
DELETE FROM post_likes WHERE post_id NOT IN (SELECT id FROM posts);
DELETE FROM post_favs  WHERE post_id NOT IN (SELECT id FROM posts);
```

### 开启邮箱验证

在 `config.php` 中：

```php
define('EMAIL_VERIFY_REQUIRED', true);
```

确保服务器 PHP `mail()` 可用，或配置 SMTP。

### 新增分区

进入管理后台 → 「分区管理」→ 填写名称/描述/颜色/图标/封面图（必填）→ 保存。

---

## 10. 部署注意事项

1. **PHP 版本**：服务器为 PHP 7.x，不支持 `match()` 表达式，代码中已用数组映射代替。

2. **MySQL 版本**：不支持 `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`，`ensure_user_columns()` 已用 `SHOW COLUMNS` 兼容。

3. **ensure_user_columns 调用**：首次部署或数据库结构有更新时，访问任意调用了该函数的页面（如 profile.php / settings.php）即可自动补全缺失字段，无需手动 ALTER。

4. **上传版 config.php**：必须包含 `SITE_URL / SITE_NAME / MAIL_FROM / EMAIL_VERIFY_REQUIRED` 四个常量，否则邮件功能异常。

5. **uploads/ 目录**：首次部署需手动创建四个子目录并设置写权限。

6. **错误页配置**：服务器需配置将对应状态码路由到 `400.php` 等文件（如 Apache 的 `ErrorDocument`）。
