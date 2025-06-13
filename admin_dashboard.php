<?php
session_start();
require_once 'db.php';

// 检查是否为管理员
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// 处理密码修改请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'change_password') {
        $user_id = $_POST['user_id'];
        $new_password = $_POST['new_password'];
        
        // 加密新密码
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // 更新密码
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            $success_message = "密码修改成功！";
        } else {
            $error_message = "密码修改失败：" . $conn->error;
        }
    }
}

// 获取所有普通用户
$stmt = $conn->prepare("SELECT id, username, email, created_at FROM users WHERE role = 'user'");
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>管理员控制台 - 图书馆管理系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .admin-header {
            background: #1a237e;
            color: white;
            padding: 1rem 0;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="m-0">管理员控制台</h2>
                </div>
                <div class="col text-end">
                    <span class="me-3">欢迎，<?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <a href="logout.php" class="btn btn-light btn-sm">退出登录</a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">用户管理</h3>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>用户名</th>
                            <th>邮箱</th>
                            <th>注册时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                                <td>
                                    <button type="button" class="btn btn-primary btn-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#changePasswordModal<?php echo $user['id']; ?>">
                                        修改密码
                                    </button>
                                </td>
                            </tr>
                            
                            <!-- 修改密码模态框 -->
                            <div class="modal fade" id="changePasswordModal<?php echo $user['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">修改用户密码</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="change_password">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                
                                                <p>正在修改用户 <strong><?php echo htmlspecialchars($user['username']); ?></strong> 的密码</p>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">新密码</label>
                                                    <input type="password" name="new_password" class="form-control" required>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                                                <button type="submit" class="btn btn-primary">确认修改</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 