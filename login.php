<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($userId, $hashedPassword, $role);
        $stmt->fetch();

        if (password_verify($password, $hashedPassword)) {
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;
            
            // 根据角色跳转到不同页面
            if ($role === 'admin') {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: index.php");
            }
            exit();
        } else {
            $error = "密码错误";
        }
    } else {
        $error = "用户名不存在";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>用户登录</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5" style="max-width: 500px;">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">用户登录</h3>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">用户名</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">密码</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">登录</button>
                </form>
                <div class="mt-3 text-center">
                    没有账号？<a href="register.php">普通用户注册</a> | <a href="admin_register.php" class="text-danger">管理员注册</a>
                </div>
                <?php if (isset($_GET['msg']) && $_GET['msg'] === 'admin_registered'): ?>
                    <div class="alert alert-success mt-3">
                        管理员注册成功！请使用新账号登录。
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>