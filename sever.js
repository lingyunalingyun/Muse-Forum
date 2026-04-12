const express = require('express');
const mysql = require('mysql2'); // 需要安装 mysql2 驱动
const app = express();

app.use(express.json()); // 让服务器能理解网页发来的 JSON 数据

// 1. 连接你预埋的数据库
const db = mysql.createConnection({
    host: 'localhost',
    user: 'root',
    password: '你的密码',
    database: 'my_website_db'
});

// 2. 编写“发布内容”的接口
app.post('/api/post', (req, res) => {
    const { content, userId } = req.body;
    
    // 核心 SQL 逻辑：预处理语句防止 SQL 注入
    const sql = "INSERT INTO posts (user_id, content, status) VALUES (?, ?, 'pending')";
    
    db.query(sql, [userId, content], (err, result) => {
        if (err) return res.status(500).json({ message: "数据库写入失败" });
        res.json({ message: "内容已提交，等待管理员审核", id: result.insertId });
    });
});

app.listen(3000, () => console.log('服务器已在 3000 端口启动...'));