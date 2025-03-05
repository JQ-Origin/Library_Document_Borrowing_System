<?php
session_start();
require_once 'db.php';

// 权限验证
if ($_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// 获取借阅趋势数据（最近30天）
$trend_data = $conn->query("SELECT DATE(borrow_date) AS date, COUNT(*) AS count 
    FROM borrow_records 
    WHERE borrow_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(borrow_date)");

// 获取热门书籍排行
$popular_books = $conn->query("SELECT b.title, COUNT(br.id) AS borrow_count 
    FROM borrow_records br
    JOIN books b ON br.book_id = b.id
    GROUP BY b.title
    ORDER BY borrow_count DESC
    LIMIT 10");
?>

<!DOCTYPE html>
<html>
<head>
    <title>借阅统计</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">图书馆管理系统</a>
            <div class="navbar-nav">
                <a class="nav-link" href="book_manage.php">图书管理</a>
                <a class="nav-link" href="stats.php">统计报表</a>
                <a class="nav-link" href="logout.php">退出登录</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <!-- 借阅趋势图表 -->
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">借阅趋势（最近30天）</div>
                    <div class="card-body">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- 热门书籍排行 -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">热门书籍TOP10</div>
                    <div class="card-body">
                        <ul class="list-group">
                            <?php while ($book = $popular_books->fetch_assoc()): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?= htmlspecialchars($book['title']) ?>
                                <span class="badge bg-primary rounded-pill"><?= $book['borrow_count'] ?></span>
                            </li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 借阅趋势图表初始化
        const ctx = document.getElementById('trendChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: [<?php while ($row = $trend_data->fetch_assoc()) echo '"'.$row['date'].'",'; ?>],
                datasets: [{
                    label: '每日借阅量',
                    data: [<?php $trend_data->data_seek(0); while ($row = $trend_data->fetch_assoc()) echo $row['count'].','; ?>],
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            }
        });
    </script>
</body>
</html>