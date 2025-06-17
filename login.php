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
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <!-- Header -->
            <div class="bg-blue-600 px-6 py-4">
                <h3 class="text-xl font-semibold text-white mb-0">用户登录</h3>
            </div>
            
            <!-- Body -->
            <div class="px-6 py-6">
                <!-- Error Message -->
                <?php if (isset($error)): ?>
                    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Success Message -->
                <?php if (isset($_GET['msg']) && $_GET['msg'] === 'admin_registered'): ?>
                    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                        管理员注册成功！请使用新账号登录。
                    </div>
                <?php endif; ?>
                
                <!-- Login Form -->
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">用户名</label>
                        <input 
                            type="text" 
                            name="username" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                            required
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">密码</label>
                        <input 
                            type="password" 
                            name="password" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                            required
                        >
                    </div>
                    
                    <button 
                        type="submit" 
                        class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200 font-medium"
                    >
                        登录
                    </button>
                </form>
                
                <!-- Registration Links -->
                <div class="mt-6 text-center text-sm text-gray-600">
                    没有账号？
                    <a href="register.php" class="text-blue-600 hover:text-blue-500 font-medium">普通用户注册</a>
                    |
                    <a href="admin_register.php" class="text-red-600 hover:text-red-500 font-medium">管理员注册</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>