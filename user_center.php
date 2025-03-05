<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// 处理密码修改
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $error = '新密码与确认密码不一致';
    } else {
        $stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if (password_verify($old_password, $result['password'])) {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $update_stmt->bind_param("si", $new_hash, $user_id);
            if ($update_stmt->execute()) {
                $success = '密码修改成功';
            } else {
                $error = '密码更新失败';
            }
        } else {
            $error = '旧密码不正确';
        }
    }
}

// 获取借阅历史
$borrow_history = $conn->query("SELECT b.title, br.borrow_date, br.return_date, br.status 
    FROM borrow_records br
    JOIN books b ON br.book_id = b.id
    WHERE br.user_id = $user_id
    ORDER BY br.borrow_date DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>个人中心</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">图书馆管理系统</a>
            <div class="navbar-nav">
                <a class="nav-link" href="index.php">首页</a>
                <a class="nav-link" href="user_center.php">个人中心</a>
                <a class="nav-link" href="logout.php">退出登录</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        个人信息
                    </div>
                    <div class="card-body">
                        <p>用户名：<?php echo $_SESSION['username']; ?></p>
                        <p>角色：<?php echo $_SESSION['role'] === 'user' ? '普通用户' : '管理员'; ?></p>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <!-- 密码修改表单 -->
                <div class="card mb-4">
                    <div class="card-header">修改密码</div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">旧密码</label>
                                <input type="password" name="old_password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">新密码</label>
                                <input type="password" name="new_password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">确认新密码</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            <button type="submit" name="change_password" class="btn btn-primary">修改密码</button>
                        </form>
                    </div>
                </div>

                <!-- 借阅历史 -->
                <div class="card">
                    <div class="card-header">借阅历史</div>
                    <div class="card-body">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>书名</th>
                                    <th>借阅日期</th>
                                    <th>归还日期</th>
                                    <th>状态</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($record = $borrow_history->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($record['title']); ?></td>
                                    <td><?php echo $record['borrow_date']; ?></td>
                                    <td><?php echo $record['return_date'] ?? '未归还'; ?></td>
                                    <td><?php echo $record['status']; ?></td>
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