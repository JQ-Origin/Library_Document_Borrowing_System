<?php
session_start();
require_once 'db.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 获取图书ID
$book_id = $_GET['book_id'] ?? 0;
if (!$book_id) {
    header("Location: index.php");
    exit();
}

// 获取图书信息
$stmt = $conn->prepare("SELECT * FROM books WHERE id = ?");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();

if (!$book) {
    header("Location: index.php");
    exit();
}

// 处理选座请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seat_id'])) {
    $seat_id = $_POST['seat_id'];
    $user_id = $_SESSION['user_id'];
    
    // 开启事务
    $conn->begin_transaction();
    
    try {
        // 检查座位是否可用
        $stmt = $conn->prepare("SELECT status FROM seats WHERE id = ? FOR UPDATE");
        $stmt->bind_param("i", $seat_id);
        $stmt->execute();
        $seat_status = $stmt->get_result()->fetch_assoc()['status'];
        
        if ($seat_status !== '空闲') {
            throw new Exception("该座位已被占用");
        }
        
        // 更新座位状态
        $stmt = $conn->prepare("UPDATE seats SET status = '已预约' WHERE id = ?");
        $stmt->bind_param("i", $seat_id);
        $stmt->execute();
        
        // 创建借阅记录
        $stmt = $conn->prepare("INSERT INTO borrow_records (user_id, book_id, seat_id, borrow_date, due_date, status) VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), '借阅中')");
        $stmt->bind_param("iii", $user_id, $book_id, $seat_id);
        $stmt->execute();
        
        // 更新图书可用数量
        $stmt = $conn->prepare("UPDATE books SET available = available - 1 WHERE id = ?");
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        
        $conn->commit();
        header("Location: user_center.php?msg=borrow_success");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

// 获取所有可用座位
$seats = $conn->query("SELECT * FROM seats WHERE status = '空闲' ORDER BY room_number, seat_number");
?>

<!DOCTYPE html>
<html>
<head>
    <title>选择座位 - 图书馆管理系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .seat-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin: 2rem 0;
        }
        .seat-item {
            border: 1px solid #ddd;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .seat-item:hover {
            background-color: #f8f9fa;
        }
        .seat-item.selected {
            background-color: #e3f2fd;
            border-color: #2196f3;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">图书馆管理系统</a>
            <div class="navbar-nav">
                <a class="nav-link" href="user_center.php">个人中心</a>
                <a class="nav-link" href="logout.php">退出登录</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h3 class="mb-4">选择阅览座位</h3>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">图书信息</h5>
                <p class="card-text">
                    <strong>书名：</strong><?= htmlspecialchars($book['title']) ?><br>
                    <strong>作者：</strong><?= htmlspecialchars($book['author']) ?><br>
                    <strong>ISBN：</strong><?= $book['isbn'] ?>
                </p>
            </div>
        </div>

        <form method="POST" id="seatForm">
            <input type="hidden" name="seat_id" id="selected_seat">
            
            <h5 class="mb-3">阅览室座位</h5>
            <div class="seat-grid">
                <?php while ($seat = $seats->fetch_assoc()): ?>
                    <div class="seat-item" data-seat-id="<?= $seat['id'] ?>">
                        <h6>阅览室 <?= $seat['room_number'] ?></h6>
                        <p class="mb-0">座位 <?= $seat['seat_number'] ?></p>
                    </div>
                <?php endwhile; ?>
            </div>

            <div class="text-center mt-4">
                <button type="submit" class="btn btn-primary" id="submitBtn" disabled>确认选座</button>
                <a href="index.php" class="btn btn-secondary">取消</a>
            </div>
        </form>
    </div>

    <script>
        document.querySelectorAll('.seat-item').forEach(item => {
            item.addEventListener('click', function() {
                // 移除其他座位的选中状态
                document.querySelectorAll('.seat-item').forEach(i => i.classList.remove('selected'));
                // 添加当前座位的选中状态
                this.classList.add('selected');
                // 设置选中的座位ID
                document.getElementById('selected_seat').value = this.dataset.seatId;
                // 启用提交按钮
                document.getElementById('submitBtn').disabled = false;
            });
        });
    </script>
</body>
</html> 