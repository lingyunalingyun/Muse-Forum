# 缪斯 MUSE 论坛 — 维护手册

> 最后更新：2026-04-20
> GitHub：https://github.com/lingyunalingyun/Muse-Forum

---

## 目录

1. [项目概览](#1-项目概览)
2. [目录结构](#2-目录结构)
3. [数据库表](#3-数据库表)
4. [模块说明](#4-模块说明)
5. [角色与权限](#5-角色与权限)
6. [经验与等级](#6-经验与等级)
7. [封禁系统](#7-封禁系统)
8. [举报系统](#8-举报系统)
9. [转发与动态](#9-转发与动态)
10. [交友页](#10-交友页)
11. [客服系统](#11-客服系统)
12. [配置说明](#12-配置说明)
13. [开发工作流](#13-开发工作流)
14. [常见维护操作](#14-常见维护操作)
15. [部署注意事项](#15-部署注意事项)

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
| `F:\Desktop\论坛开发_本地版` | 开发版，本地调试用，含真实 DB 密码，代码无注释 |
| `F:\Desktop\论坛开发_上传版` | 上传版，敏感信息脱敏，含完整注释，推送 GitHub |

**工作流：** 本地版修改 → 复制到上传版 → 上传版安全处理 → 推送 GitHub

---

## 2. 目录结构

```
/
├── config.php                  全局配置（DB 连接、站点常量）
├── index.php                   首页（推荐帖 2列大图网格 + 侧栏）
├── square.php                  广场（全部帖子，分页+分区筛选，黑名单过滤）
├── categories.php              分区入口（PS5 游戏库风格 16:10 大图卡片）
├── dating.php                  交友页（滚动卡片墙，投放/查看交友卡片）
├── search.php                  全站搜索（帖子+用户 Tab）
├── topic.php                   话题页（#标签聚合帖子列表）
├── style.css                   全站基础样式
│
├── includes/
│   ├── header.php              全局导航栏（粘性顶栏+封禁检测+封禁横幅）
│   ├── exp_helper.php          核心辅助函数库（经验/等级/角色/邮件/DB迁移/可见性）
│   ├── repost_card.php         转发卡片渲染函数 render_repost_card()
│   └── text_format.php         文本格式化（#话题 @提及 渲染）
│
├── pages/
│   ├── login.php               登录页
│   ├── register.php            注册页（含邮箱验证状态提示）
│   ├── logout.php              登出（销毁 session）
│   ├── verify.php              邮箱验证（token 24h）
│   ├── forgot_password.php     忘记密码页
│   ├── reset_password.php      重置密码页（token 1h）
│   ├── profile.php             用户主页（头像/等级/帖子/收藏/点赞 Tab/关注粉丝弹窗）
│   ├── edit_profile.php        编辑资料（头像/昵称/签名/邮箱）
│   ├── settings.php            个人设置（隐私+黑名单）
│   ├── publish.php             发帖页（WangEditor）
│   ├── post.php                帖子详情（内容+评论树+可见性检查+举报）
│   ├── moments.php             动态页（自己+关注者帖子，含转发）
│   ├── notices.php             社区公告列表
│   ├── notifications.php       消息中心（含帖子引用卡片）
│   ├── report_modal.php        举报弹窗组件（被 post.php / profile.php include）
│   ├── admin.php               管理后台（待审核帖子审核）
│   ├── admin_ban.php           封禁管理后台（限时/永久，快速解封）
│   ├── admin_categories.php    分区管理（创建/编辑/删除）
│   ├── admin_messages.php      私信查询后台
│   ├── admin_reports.php       举报管理后台（删帖/封号/驳回/处理）
│   ├── cs_chat.php             客服聊天页（用户端）
│   └── cs_panel.php            客服工作台（客服/管理端）
│
├── actions/                    AJAX/表单处理端点（POST）
│   ├── auth.php                认证（注册/登录/找回密码/重置密码/重发验证）
│   ├── save.php                发帖/保存草稿（支持转发 repost_id）
│   ├── post_action.php         帖子点赞/收藏 toggle（返回 JSON）
│   ├── post_delete.php         删除帖子（级联删评论/点赞/收藏）
│   ├── post_edit.php           编辑帖子（10 分钟冷却）
│   ├── post_recommend.php      推荐/取消推荐（admin/owner）
│   ├── comment_save.php        提交评论/回复
│   ├── comment_delete.php      删除评论（级联子回复）
│   ├── comment_like.php        评论点赞 toggle（返回 JSON）
│   ├── comment_top.php         评论置顶 toggle（admin/owner）
│   ├── follow_toggle.php       关注/取消关注（返回 JSON）
│   ├── block_user.php          拉黑/取消拉黑（拉黑时自动解除双向关注）
│   ├── ban_user.php            封禁/解封（支持限时+永久，admin/owner）
│   ├── message_send.php        发送私信（支持帖子引用卡片 ref_post_id）
│   ├── report_submit.php       提交举报（type: post|user）
│   ├── social_card_save.php    交友卡片投放/删除/管理员删除
│   ├── upload_image.php        WangEditor 图片上传（返回 errno/url JSON）
│   ├── upload_attachment.php   附件上传
│   ├── user_search.php         @提及用户名搜索（返回 JSON）
│   └── cs_action.php           客服 action 端点
│
├── uploads/
│   ├── avatars/                用户头像
│   ├── posts/                  帖子内图片
│   ├── attachments/            帖子附件
│   └── categories/             分区封面图
│
└── 400.php / 401.php / 403.php / 404.php / 429.php / 500.php / 502.php / 503.php
                                自定义错误页（暗色终端风格）
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
| `reports` | 举报记录 |
| `social_cards` | 交友卡片 |

### users 表关键字段

| 字段 | 说明 |
|------|------|
| `id` | 自增主键 |
| `userid` | 6位随机数字 ID（注册时生成，用于登录） |
| `mid` | 8位随机数字 ID（私信系统用） |
| `email` | 邮箱（唯一） |
| `username` | 昵称（唯一） |
| `password` | bcrypt 哈希 |
| `role` | `owner` / `admin` / `sponsor` / `user` |
| `is_banned` | 0=正常，1=封禁 |
| `ban_reason` | 封禁原因 |
| `ban_until` | 封禁到期时间（NULL=永久）|
| `banned_by` | 执行封禁的管理员 user_id |
| `exp` | 当前经验值 |
| `level` | 当前等级（1-6，由 add_exp 自动更新） |
| `login_streak` | 连续登录天数 |
| `last_login_date` | 最后登录日期 |
| `email_verified` | 邮箱是否验证（0/1） |
| `verify_token` | 验证 token（24h） |
| `verify_token_expires` | 验证 token 过期时间 |
| `verify_resend_at` | 最后重发验证邮件时间（60s冷却） |
| `reset_token` | 重置密码 token（1h） |
| `reset_token_expires` | 重置 token 过期时间 |
| `show_followers` | 粉丝列表是否公开（0/1） |
| `show_following` | 关注列表是否公开（0/1） |
| `post_visibility` | 帖子可见性默认值 |
| `is_cs` | 是否为客服人员（0/1） |

### posts 表关键字段

| 字段 | 说明 |
|------|------|
| `status` | `草稿` / `待审核` / `已发布` |
| `is_recommend` | 1=出现在首页推荐网格 |
| `is_notice` | 1=公告帖 |
| `category_id` | 所属分区（NULL=无分区） |
| `repost_id` | 转发原帖 ID（NULL=原创帖） |
| `attachments` | 附件信息 JSON 字符串 |
| `edited_at` | 最后编辑时间（10 分钟冷却依据） |
| `approved_by` | 审核人 user_id |
| `approved_at` | 审核通过时间 |

### reports 表字段

| 字段 | 说明 |
|------|------|
| `reporter_id` | 举报人 user_id |
| `type` | `post`（举报帖子）/ `user`（举报用户） |
| `target_id` | 被举报的帖子或用户 ID |
| `reason` | 举报原因（分类） |
| `detail` | 详情说明 |
| `status` | `pending`（待处理）/ `handled`（已处理）/ `dismissed`（驳回） |

### social_cards 表字段

| 字段 | 说明 |
|------|------|
| `user_id` | 投放用户（UNIQUE，每人只能有一张） |
| `gender` | 性别（male/female） |
| `age_range` | 年龄段（如 "20~25"，最小 18） |
| `intro` | 介绍（max 300字） |
| `tags` | 标签 JSON（最多 4 个，每个 max 10字） |
| `is_active` | 是否显示在卡片墙 |

---

## 4. 模块说明

### 认证流程（actions/auth.php）

```
注册 → 创建 users 记录 → EMAIL_VERIFY_REQUIRED?
       ├─ true  → 发验证邮件 → 用户点链接 → verify.php → email_verified=1
       └─ false → 直接 email_verified=1 → 跳登录页

登录 → 密码验证 → 封禁到期检查（自动解封）→ 邮箱验证检查 → 写 session → 登录经验奖励
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
| `repost` | 有人转发了你的帖子 |

### 帖子可见性（post_visibility）

| 值 | 说明 |
|----|------|
| `public` | 所有人可见（默认） |
| `followers` | 仅关注了我的人可见 |
| `following` | 仅我关注的人可见 |
| `mutual` | 仅互相关注可见 |
| `private` | 仅自己可见 |

> admin / owner 绕过所有可见性限制。检查函数 `can_see_posts()` 在 `includes/exp_helper.php`。

### 黑名单系统

- `user_blocks` 表：blocker_id + blocked_id
- 拉黑时自动解除双向关注
- 生效范围：profile.php 隐藏帖子区/操作按钮；post.php 拒绝被作者拉黑的访问；square.php 过滤被拉黑用户的帖子

---

## 5. 角色与权限

| role | 颜色 | 标签 | 可封禁 |
|------|------|------|--------|
| `owner` | `#f85149` 红 | ★ 站长 | 可封禁 admin / user |
| `admin` | `#a78bfa` 紫 | 管理员 | 只能封禁 user |
| `sponsor` | `#c084fc` 发光紫 | 赞助者 | — |
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
| `add_exp($conn, $user_id, $amount)` | 增加 EXP 并自动更新 level，返回 `['exp', 'level']` |
| `get_level_by_exp($exp)` | 根据 EXP 返回等级数字（1-6） |
| `calc_login_exp($streak)` | 根据连续天数计算当日奖励 EXP |
| `get_level_name($level)` | 返回等级名称字符串 |
| `get_level_color($level)` | 返回等级对应颜色十六进制 |

---

## 7. 封禁系统

### 封禁字段

- `is_banned=1` + `ban_reason` + `ban_until`（NULL=永久，有值=限时）+ `banned_by`
- `ensure_user_columns()` 自动补全这些字段

### 拦截点

| 位置 | 说明 |
|------|------|
| `actions/auth.php` | 登录时检测；到期自动解封 |
| `includes/header.php` | 每次页面加载刷新封禁状态，限时封禁到期自动解封 |
| `actions/comment_save.php` | 拦截评论 |
| `actions/post_action.php` | 拦截点赞/收藏 |
| `actions/follow_toggle.php` | 拦截关注 |
| `actions/message_send.php` | 拦截发私信 |
| `actions/save.php` | 拦截发帖 |
| `actions/social_card_save.php` | 拦截投放交友卡片 |
| `pages/publish.php` | 拦截访问发帖页 |
| `pages/edit_profile.php` | 拦截访问编辑资料页 |
| `pages/settings.php` | 拦截访问设置页 |

### 封禁横幅

被封用户登录后，`header.php` 顶部显示黄-红渐变横幅，内容含封禁原因、到期日期、客服链接。

### 封禁管理

- 后台：`pages/admin_ban.php`（admin/owner 可访问）
- 操作 action：`actions/ban_user.php`（POST: action, user_id, reason, ban_until）

---

## 8. 举报系统

| 位置 | 说明 |
|------|------|
| `pages/report_modal.php` | 共享举报弹窗组件（post.php + profile.php include） |
| `actions/report_submit.php` | 举报提交端点（type: post/user） |
| `pages/admin_reports.php` | 管理员查看和处理举报 |

导航抽屉红点 = 待审核帖子数 + 待处理举报数（分开计数）。

---

## 9. 转发与动态

### 转发

- `posts.repost_id`：转发帖引用原帖 ID
- `messages.ref_post_id`：私信中携带的帖子卡片
- `includes/repost_card.php`：`render_repost_card($conn, $post_id, $base)` 渲染 HTML 卡片
- `actions/save.php`：接收 `repost_id` 参数，转发帖允许空标题/内容
- `actions/message_send.php`：接收 `ref_post_id` 参数，内容和帖子引用至少一个

### 动态页（pages/moments.php）

展示当前用户 + 已关注者发布的帖子（含转发），按时间倒序分页。导航栏"动态"入口（已登录可见）。

---

## 10. 交友页

路由：`dating.php`，导航栏"交友"入口。

| 要素 | 说明 |
|------|------|
| 卡片墙 | 6列，CSS 无缝上下滚动（奇列 scrollUp / 偶列 scrollDown），时长 58-80s |
| 卡片比例 | `aspect-ratio: 5/7`（扑克牌），背景为用户头像模糊处理 |
| 性别光晕 | 女=粉红 / 男=蓝，`box-shadow` 四周散发 |
| 点击卡片 | 弹出详情弹窗，可查看主页、发私信 |
| 投放卡片 | 性别（必填）+ 年龄段（≥18，格式 20~25）+ 介绍（max 300）+ 4个标签（max 10字/个） |
| 过滤 | `is_active=1` + 双向黑名单过滤 |
| 管理员 | admin/owner 可在弹窗内删除他人卡片 |

---

## 11. 客服系统

| 文件 | 说明 |
|------|------|
| `pages/cs_chat.php` | 用户端客服聊天页 |
| `pages/cs_panel.php` | 客服工作台（is_cs=1 或 admin/owner） |
| `actions/cs_action.php` | 客服 action 端点 |

设置 `users.is_cs = 1` 即可赋予用户客服权限。

---

## 12. 配置说明

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

## 13. 开发工作流

```
1. 在 论坛开发_本地版 修改代码并本地测试（代码无注释，简洁）

2. 确认没问题后，将修改的文件复制到 论坛开发_上传版

3. 在上传版中：
   - config.php 已脱敏，保持不变
   - 为新增文件添加 docblock 注释
   - 检查是否有 console.log / var_dump / die() 等调试代码

4. git add → git commit → git push 到 GitHub

5. 服务器从 GitHub 拉取更新
```

---

## 14. 常见维护操作

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

### 手动封禁用户（永久）

```sql
UPDATE users SET is_banned = 1, ban_reason = '封禁原因', ban_until = NULL WHERE username = '用户名';
```

### 手动封禁用户（限时7天）

```sql
UPDATE users SET is_banned = 1, ban_reason = '封禁原因', ban_until = DATE_ADD(NOW(), INTERVAL 7 DAY) WHERE username = '用户名';
```

### 手动解封用户

```sql
UPDATE users SET is_banned = 0, ban_reason = NULL, ban_until = NULL, banned_by = NULL WHERE username = '用户名';
```

### 设置客服人员

```sql
UPDATE users SET is_cs = 1 WHERE username = '用户名';
```

### 清理孤立数据（删帖后遗留）

```sql
DELETE FROM comments    WHERE post_id NOT IN (SELECT id FROM posts);
DELETE FROM post_likes  WHERE post_id NOT IN (SELECT id FROM posts);
DELETE FROM post_favs   WHERE post_id NOT IN (SELECT id FROM posts);
DELETE FROM post_topics WHERE post_id NOT IN (SELECT id FROM posts);
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

## 15. 部署注意事项

1. **PHP 版本**：服务器为 PHP 7.x，不支持 `match()` 表达式，代码中已用数组映射代替。

2. **MySQL 版本**：不支持 `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`，`ensure_user_columns()` 已用 `SHOW COLUMNS` 兼容。

3. **ensure_user_columns 调用**：首次部署或数据库结构有更新时，访问任意调用了该函数的页面（如 profile.php / settings.php）即可自动补全缺失字段，无需手动 ALTER。

4. **上传版 config.php**：必须包含 `SITE_URL / SITE_NAME / MAIL_FROM / EMAIL_VERIFY_REQUIRED` 四个常量，否则邮件功能异常。

5. **uploads/ 目录**：首次部署需手动创建四个子目录并设置写权限。

6. **错误页配置**：服务器需配置将对应状态码路由到 `400.php` 等文件。Apache 配置示例：

   ```apache
   ErrorDocument 400 /400.php
   ErrorDocument 401 /401.php
   ErrorDocument 403 /403.php
   ErrorDocument 404 /404.php
   ErrorDocument 429 /429.php
   ErrorDocument 500 /500.php
   ErrorDocument 502 /502.php
   ErrorDocument 503 /503.php
   ```

7. **ban_until 字段**：`NULL` = 永久封禁，有值 = 限时封禁。到期后 header.php 会自动解封，无需手动处理。
