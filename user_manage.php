<?php
session_start();
include 'db.php';

// 权限验证
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// 获取用户列表
$sql = "SELECT * FROM users ORDER BY id DESC";
$result = $conn->query($sql);

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_user'])) {
        $userId = $_POST['user_id'];
        $username = $conn->real_escape_string($_POST['username']);
        $email = $conn->real_escape_string($_POST['email']);
        
        $updateSql = "UPDATE users SET username='$username', email='$email' WHERE id=$userId";
        $conn->query($updateSql);
    } 
    
    if (isset($_POST['reset_password'])) {
        $userId = $_POST['user_id'];
        $newPassword = password_hash('123456', PASSWORD_DEFAULT); // 默认重置密码
        $updateSql = "UPDATE users SET password='$newPassword' WHERE id=$userId";
        $conn->query($updateSql);
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>图书管理 - 图书馆管理系统</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- 顶部头部 -->
    <div class="bg-gradient-to-r from-green-800 to-blue-800 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <i class="fas fa-book text-3xl mr-4"></i>
                    <div>
                        <h1 class="text-2xl font-bold">用户管理</h1>
                        <p class="text-green-200 text-sm mt-1">修改、编辑用户信息</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <p class="text-sm text-green-200">管理员</p>
                        <p class="font-semibold"><?php echo htmlspecialchars($_SESSION['username']); ?></p>
                    </div>
                    <a href="index.php" class="bg-white text-green-800 px-4 py-2 rounded-lg hover:bg-green-50 transition duration-200 flex items-center">
                        <i class="fas fa-home mr-2"></i>
                        返回主页
                    </a>
                </div>
            </div>
        </div>
    </div>
    <!-- 导航栏 -->
    <nav class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                    <?php if ($role === 'user'): ?>
                        <a href="user_center.php" class="text-white hover:text-blue-200 transition duration-200 flex items-center">
                            <i class="fas fa-user mr-2"></i>个人中心
                        </a>
                    <?php elseif ($role === 'admin'): ?>
                        <a href="book_manage.php" class="text-white hover:text-blue-200 transition duration-200 flex items-center">
                            <i class="fas fa-book-open mr-2"></i>图书管理
                        </a>
                        <a href="borrow_manage.php" class="text-white hover:text-blue-200 transition duration-200 flex items-center">
                            <i class="fas fa-clipboard-list mr-2"></i>借阅管理
                        </a>
                    <?php endif; ?>
        </div>
    </nav>

    <nav class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex space-x-8 py-4">
                <a href="user_manage.php" class="text-green-600 border-b-2 border-green-600 pb-2 px-1 text-sm font-medium">
                    <i class="fas fa-user-cog mr-2"></i>用户管理
                </a>
                <a href="book_manage.php" class="text-gray-500 hover:text-gray-700 hover:border-gray-300 border-b-2 border-transparent pb-2 px-1 text-sm font-medium transition duration-200">
                    <i class="fas fa-book mr-2"></i>图书管理
                </a>
                <a href="borrow_manage.php" class="text-gray-500 hover:text-gray-700 hover:border-gray-300 border-b-2 border-transparent pb-2 px-1 text-sm font-medium transition duration-200">
                    <i class="fas fa-clipboard-list mr-2"></i>借阅管理
                </a>
            </div>
        </div>
    </nav>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- 统计卡片模块 -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
                <div class="flex items-center">
                    <i class="fas fa-users text-blue-500 text-3xl mr-4"></i>
                    <div>
                        <p class="text-gray-600 text-sm font-medium">注册用户</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $result->num_rows; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
                <div class="flex items-center">
                    <i class="fas fa-user-check text-green-500 text-3xl mr-4"></i>
                    <div>
                        <p class="text-gray-600 text-sm font-medium">活跃用户</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo getActiveUsers(); ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-purple-500">
                <div class="flex items-center">
                    <i class="fas fa-user-lock text-purple-500 text-3xl mr-4"></i>
                    <div>
                        <p class="text-gray-600 text-sm font-medium">管理员</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo getAdminCount(); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 主内容区域 -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <div class="lg:col-span-1">
                <!-- 用户信息侧边栏 -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4">
                        <h3 class="text-lg font-semibold text-white"><i class="fas fa-chart-pie mr-2"></i>用户分布</h3>
                    </div>
                    <div class="p-6">
                        <!-- 添加饼图或统计图表 -->
                    </div>
                </div>
            </div>

            <div class="lg:col-span-3">
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="p-6">
            <h2 class="text-2xl font-semibold mb-6">用户管理</h2>

            <!-- 用户列表表格 -->
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">用户名</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">邮箱</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">操作</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap"><?= $row['id'] ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?= $row['username'] ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?= $row['email'] ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <button onclick="openEditModal(<?= $row['id'] ?>, '<?= $row['username'] ?>', '<?= $row['email'] ?>')"
                                class="text-blue-600 hover:text-blue-900 mr-4">
                                <i class="fas fa-edit"></i> 编辑
                            </button>
                            <form action="user_manage.php" method="post" class="inline">
                                <input type="hidden" name="user_id" value="<?= $row['id'] ?>">
                                <button type="submit" name="reset_password" 
                                    class="text-yellow-600 hover:text-yellow-900"
                                    onclick="return confirm('确定重置密码为123456吗？')">
                                    <i class="fas fa-key"></i> 重置密码
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
                    </div>

                    <!-- 分页控件 -->
                    <div class="mt-6 flex justify-between items-center">
                        <span class="text-sm text-gray-600">
                            显示 <?php echo $start + 1; ?> - <?php echo min($end, $totalUsers); ?> 共 <?php echo $totalUsers; ?> 用户
                        </span>
                        <div class="space-x-2">
                            <?php if ($currentPage > 1): ?>
                                <a href="?page=<?php echo $currentPage - 1; ?>" class="px-4 py-2 border rounded-md hover:bg-gray-50">上一页</a>
                            <?php endif; ?>
                            <?php if ($currentPage < $totalPages): ?>
                                <a href="?page=<?php echo $currentPage + 1; ?>" class="px-4 py-2 border rounded-md hover:bg-gray-50">下一页</a>
                            <?php endif; ?>
                        </div>
                    </div>
        </div>
    </div>

    <!-- 编辑模态框 -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden">
        <div class="flex items-center justify-center min-h-screen">
            <div class="bg-white rounded-lg p-6 w-96">
                <h3 class="text-xl font-semibold mb-4">编辑用户信息</h3>
                <form id="editForm" method="post">
                    <input type="hidden" name="user_id" id="editUserId">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">用户名</label>
                        <input type="text" name="username" id="editUsername"
                            class="w-full px-3 py-2 border rounded-md">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">邮箱</label>
                        <input type="email" name="email" id="editEmail"
                            class="w-full px-3 py-2 border rounded-md">
                    </div>
                    <div class="flex justify-end">
                        <button type="button" onclick="closeEditModal()"
                            class="bg-gray-500 text-white px-4 py-2 rounded-md mr-2">取消</button>
                        <button type="submit" name="update_user"
                            class="bg-blue-600 text-white px-4 py-2 rounded-md">保存</button>
                    </div>
                </form>
            </div>
                    </div>
        </div>
    </div>

    <script>
        function openEditModal(id, username, email) {
            document.getElementById('editUserId').value = id;
            document.getElementById('editUsername').value = username;
            document.getElementById('editEmail').value = email;
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
    </script>

</body>
</html>