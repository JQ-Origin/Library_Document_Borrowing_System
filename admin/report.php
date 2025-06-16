<?php
session_start();
require_once 'db.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// 处理数据导出
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    
    // 设置文件名和头信息
    $filename = "library_report_".date('Ymd').".".$type;
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=$filename");

    // 获取数据
    $data = $conn->query("SELECT 
        b.title AS 书名,
        b.author AS 作者,
        b.isbn AS ISBN,
        COUNT(br.id) AS 借阅次数,
        MAX(br.borrow_date) AS 最后借阅日期
        FROM books b
        LEFT JOIN borrow_records br ON b.id=br.book_id
        GROUP BY b.id");

    // 生成表格
    echo "书名\t作者\tISBN\t借阅次数\t最后借阅日期\n";
    while ($row = $data->fetch_assoc()) {
        echo implode("\t", $row)."\n";
    }
    exit;
}

// 获取逾期记录
$overdue = $conn->query("SELECT 
    u.username,
    b.title,
    DATEDIFF(NOW(), br.borrow_date) AS 逾期天数
    FROM borrow_records br
    JOIN users u ON br.user_id=u.id
    JOIN books b ON br.book_id=b.id
    WHERE br.status='借出中' 
    AND DATEDIFF(NOW(), br.borrow_date) > 30");
?>

<!DOCTYPE html>
<html>
<head>
    <title>数据报表</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">图书馆管理系统</a>
            <div class="navbar-nav">
                <a class="nav-link" href="book_manage.php">图书管理</a>
                <a class="nav-link" href="stats.php">统计报表</a>
                <a class="nav-link" href="report.php">数据导出</a>
                <a class="nav-link" href="logout.php">退出登录</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <!-- 数据导出 -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">数据导出</div>
                    <div class="card-body">
                        <div class="btn-group">
                            <a href="report.php?export=xls" class="btn btn-success">导出Excel</a>
                            <a href="report.php?export=csv" class="btn btn-primary">导出CSV</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 逾期管理 -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-warning">逾期提醒（30天以上未归还）</div>
                    <div class="card-body">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>用户名</th>
                                    <th>书名</th>
                                    <th>逾期天数</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $overdue->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $row['username'] ?></td>
                                    <td><?= $row['title'] ?></td>
                                    <td class="text-danger"><?= $row['逾期天数'] ?>天</td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>