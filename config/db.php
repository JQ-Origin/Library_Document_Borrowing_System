<?php
/*
 * @Author: OriginYu
 * @Date: 2025-06-13 15:34:56
 * @LastEditors: OriginYu
 * @LastEditTime: 2025-06-13 15:35:39
 * @Description: 
 * @FilePath: \Library_Document_Borrowing_System\db.php
 */
$servername = 'localhost';
$username = 'library_admin';
$password = 'library_passwd';
$dbname = 'library';
// 创建数据库连接
$conn = new mysqli($servername, $username, $password, $dbname);

// 检查连接
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

// 设置字符集
$conn->set_charset('utf8mb4');

// 数据库初始化检查
$tables = $conn->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '".$dbname."' AND TABLE_NAME IN ('users','books','borrow_records')")->num_rows;

if ($tables < 3) {
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role ENUM('user','admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS books (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(100) NOT NULL,
    isbn VARCHAR(20) UNIQUE NOT NULL,
    category VARCHAR(50),
    total INT(11) DEFAULT 0,
    available INT(11) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS borrow_records (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    book_id INT(11) NOT NULL,
    borrow_date DATE NOT NULL,
    return_date DATE DEFAULT NULL,
    status ENUM('借出中','已归还') DEFAULT '借出中',
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (book_id) REFERENCES books(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

    if (!$conn->multi_query($sql)) {
        die("数据库初始化失败: ".$conn->error);
    }
    
    // 清空多查询结果
    while ($conn->more_results()) {
        $conn->next_result();
    }
}

?>