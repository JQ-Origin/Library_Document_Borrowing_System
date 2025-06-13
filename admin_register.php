<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $email = $_POST['email'];
    $admin_code = $_POST['admin_code'];
    
    // 验证管理员注册码（这里使用一个固定的码，实际应用中应该更安全）
    if ($admin_code !== "ADM") {
        $error = "管理员注册码错误！";
    } else {
        // 检查用户名是否已存在
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "用户名已存在";
        } else {
            // 插入新管理员用户
            $role = 'admin'; // 直接设置为管理员角色
            $stmt = $conn->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $password, $email, $role);
            
            if ($stmt->execute()) {
                header("Location: login.php?msg=admin_registered");
                exit();
            } else {
                $error = "注册失败: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>管理员注册 - 图书馆管理系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .admin-register-form {
            max-width: 400px;
            margin: 50px auto;
            padding: 20px;
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="admin-register-form">
            <h2 class="text-center mb-4">管理员注册</h2>
            
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

                <div class="mb-3">
                    <label class="form-label">电子邮箱</label>
                    <input type="email" name="email" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">管理员注册码</label>
                    <input type="password" name="admin_code" class="form-control" required>
                    <div class="form-text text-muted">请输入管理员注册码（ADMIN2024）</div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">注册</button>
                    <a href="login.php" class="btn btn-link">返回登录</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html> 