<?php
session_start();
?>

<nav class="bg-white shadow-lg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <a href="index.php" class="text-xl font-bold text-gray-800">图书馆管理系统</a>
            </div>
            <div class="flex items-center space-x-4">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="user_center.php" class="text-gray-600 hover:text-gray-900 px-3 py-2 text-sm font-medium">
                        <i class="fas fa-user-circle mr-2"></i>个人中心
                    </a>
                    <a href="logout.php" class="text-red-600 hover:text-red-800 px-3 py-2 text-sm font-medium">
                        <i class="fas fa-sign-out-alt mr-2"></i>退出
                    </a>
                <?php else: ?>
                    <a href="login.php" class="text-gray-600 hover:text-gray-900 px-3 py-2 text-sm font-medium">登录</a>
                    <a href="register.php" class="text-gray-600 hover:text-gray-900 px-3 py-2 text-sm font-medium">注册</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>