<?php
session_start();
require_once 'db.php';

// 权限验证
if ($_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// 处理归还操作
if (isset($_GET['return'])) {
    $borrow_id = $_GET['return'];
    
    // 开启事务
    $conn->begin_transaction();
    
    try {
        // 获取借阅记录和图书信息
        $stmt = $conn->prepare("SELECT book_id FROM borrow_records WHERE id=?");
        $stmt->bind_param("i", $borrow_id);
        $stmt->execute();
        $book_id = $stmt->get_result()->fetch_assoc()['book_id'];
        $stmt->close();

        // 更新借阅记录状态
        $stmt = $conn->prepare("UPDATE borrow_records SET return_date=NOW(), status='已归还' WHERE id=?");
        $stmt->bind_param("i", $borrow_id);
        $stmt->execute();
        $stmt->close();

        // 增加图书可用数量
        $stmt = $conn->prepare("UPDATE books SET available=available+1 WHERE id=?");
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        header("Location: borrow_manage.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        die("操作失败: ".$e->getMessage());
    }
}

// 获取所有借阅记录
$records = $conn->query("SELECT br.*, u.username, b.title FROM borrow_records br 
    JOIN users u ON br.user_id=u.id 
    JOIN books b ON br.book_id=b.id 
    ORDER BY br.borrow_date DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>借阅管理</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">图书馆管理系统</a>
            <div class="navbar-nav">
                <a class="nav-link" href="book_manage.php">图书管理</a>
                <a class="nav-link" href="borrow_manage.php">借阅管理</a>
                <a class="nav-link" href="logout.php">退出登录</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h3 class="mb-4">借阅管理</h3>
        
        <div class="card">
            <div class="card-header">借阅记录</div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>借阅人</th>
                            <th>图书名称</th>
                            <th>借阅日期</th>
                            <th>应还日期</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($record = $records->fetch_assoc()): ?>
                        <tr>
                            <td><?= $record['username'] ?></td>
                            <td><?= htmlspecialchars($record['title']) ?></td>
                            <td><?= $record['borrow_date'] ?></td>
                            <td><?= $record['due_date'] ?></td>
                            <td>
                                <span class="badge <?= $record['status'] === '已归还' ? 'bg-success' : 'bg-warning' ?>">
                                    <?= $record['status'] ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($record['status'] === '借阅中'): ?>
                                <a href="borrow_manage.php?return=<?= $record['id'] ?>" 
                                    class="btn btn-sm btn-success"
                                    onclick="return confirm('确认归还该图书？')">
                                    归还
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>