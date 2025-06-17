<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];

// 获取系统统计数据
$stats = [];
try {
    // 总图书数
    $stmt = $conn->prepare("SELECT SUM(total) as total_books FROM books");
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_books'] = $result->fetch_assoc()['total_books'];

    // 可借阅图书数
    $stmt = $conn->prepare("SELECT SUM(available) as available_books FROM books ");
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['available_books'] = $result->fetch_assoc()['available_books'];

    // 当前借阅数
    $stmt = $conn->prepare("SELECT COUNT(*) as active_borrows FROM borrow_records WHERE status = '借出中'");
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['active_borrows'] = $result->fetch_assoc()['active_borrows'];

    // 用户借阅记录
    if ($role === 'user') {
        $stmt = $conn->prepare("SELECT b.title, b.author, br.borrow_date, br.return_date, br.status 
                               FROM borrow_records br 
                               JOIN books b ON br.book_id = b.id 
                               WHERE br.user_id = ? 
                               ORDER BY br.borrow_date DESC 
                               LIMIT 5");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $user_borrows = $stmt->get_result();
    }

    // 热门图书
    $stmt = $conn->prepare("SELECT b.title, b.author, COUNT(br.id) as borrow_count 
                           FROM books b 
                           LEFT JOIN borrow_records br ON b.id = br.book_id 
                           GROUP BY b.id 
                           ORDER BY borrow_count DESC 
                           LIMIT 5");
    $stmt->execute();
    $popular_books = $stmt->get_result();

} catch (Exception $e) {
    $error = "数据加载失败";
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>图书馆管理系统</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- 导航栏 -->
    <nav class="bg-blue-600 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <i class="fas fa-book text-white text-2xl mr-3"></i>
                    <h1 class="text-white text-xl font-bold">图书馆管理系统</h1>
                </div>
                <div class="flex items-center space-x-6">
                    <?php if ($role === 'user'): ?>
                        <a href="user_center.php" class="text-white hover:text-blue-200 transition duration-200 flex items-center">
                            <i class="fas fa-user mr-2"></i>个人中心
                        </a>
                    <?php elseif ($role === 'admin'): ?>
                        <a href="user_manage.php" class="text-white hover:text-blue-200 transition duration-200 flex items-center">
                            <i class="fa fa-user-cog mr-2"></i>用户管理
                        </a>
                        <a href="book_manage.php" class="text-white hover:text-blue-200 transition duration-200 flex items-center">
                            <i class="fas fa-book-open mr-2"></i>图书管理
                        </a>
                        <a href="borrow_manage.php" class="text-white hover:text-blue-200 transition duration-200 flex items-center">
                            <i class="fas fa-clipboard-list mr-2"></i>借阅管理
                        </a>
                    <?php endif; ?>
                    <a href="logout.php" class="text-white hover:text-blue-200 transition duration-200 flex items-center">
                        <i class="fas fa-sign-out-alt mr-2"></i>退出登录
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- 顶部统计卡片 -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
                <div class="flex items-center">
                    <i class="fas fa-books text-blue-500 text-3xl mr-4"></i>
                    <div>
                        <p class="text-gray-600 text-sm font-medium">总图书数</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $stats['total_books'] ?? 0; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
                <div class="flex items-center">
                    <i class="fas fa-book-open text-green-500 text-3xl mr-4"></i>
                    <div>
                        <p class="text-gray-600 text-sm font-medium">可借阅</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $stats['available_books'] ?? 0; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-orange-500">
                <div class="flex items-center">
                    <i class="fas fa-hand-holding text-orange-500 text-3xl mr-4"></i>
                    <div>
                        <p class="text-gray-600 text-sm font-medium">当前借阅</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $stats['active_borrows'] ?? 0; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- 左侧用户信息 -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4">
                        <h3 class="text-lg font-semibold text-white flex items-center">
                            <i class="fas fa-user-circle mr-2"></i>
                            欢迎回来
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="text-center">
                            <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-user text-blue-500 text-2xl"></i>
                            </div>
                            <h4 class="text-xl font-semibold text-gray-900"><?php echo htmlspecialchars($_SESSION['username']); ?></h4>
                            <p class="text-gray-600 mt-2">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?php echo $role === 'admin' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                    <i class="fas <?php echo $role === 'admin' ? 'fa-crown' : 'fa-user'; ?> mr-1"></i>
                                    <?php echo $role === 'user' ? '普通用户' : '管理员'; ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- 快捷操作 -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b">
                        <h3 class="text-lg font-semibold text-gray-900">快捷操作</h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-3">
                            <?php if ($role === 'user'): ?>
                                <a href="search.php" class="flex items-center p-3 rounded-lg hover:bg-blue-50 transition duration-200 group">
                                    <i class="fas fa-search text-blue-500 mr-3 group-hover:scale-110 transition transform"></i>
                                    <span class="text-gray-700 group-hover:text-blue-600">搜索图书</span>
                                </a>
                                <a href="user_center.php" class="flex items-center p-3 rounded-lg hover:bg-green-50 transition duration-200 group">
                                    <i class="fas fa-history text-green-500 mr-3 group-hover:scale-110 transition transform"></i>
                                    <span class="text-gray-700 group-hover:text-green-600">借阅历史</span>
                                </a>
                            <?php else: ?>
                                <a href="book_manage.php" class="flex items-center p-3 rounded-lg hover:bg-blue-50 transition duration-200 group">
                                    <i class="fas fa-plus-circle text-blue-500 mr-3 group-hover:scale-110 transition transform"></i>
                                    <span class="text-gray-700 group-hover:text-blue-600">添加图书</span>
                                </a>
                                <a href="borrow_manage.php" class="flex items-center p-3 rounded-lg hover:bg-orange-50 transition duration-200 group">
                                    <i class="fas fa-tasks text-orange-500 mr-3 group-hover:scale-110 transition transform"></i>
                                    <span class="text-gray-700 group-hover:text-orange-600">处理借阅</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 右侧主要内容 -->
            <div class="lg:col-span-2 space-y-6">
                <!-- 图书搜索模块 -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-green-500 px-6 py-4">
                        <h3 class="text-lg font-semibold text-white flex items-center">
                            <i class="fas fa-search mr-2"></i>
                            图书查询
                        </h3>
                    </div>
                    <div class="p-6">
                        <form method="GET" action="search.php" class="space-y-4">
                            <div class="flex flex-col sm:flex-row gap-3">
                                <div class="flex-1">
                                    <input 
                                        type="text" 
                                        name="keyword" 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition duration-200" 
                                        placeholder="输入书名、作者或ISBN搜索图书..."
                                    >
                                </div>
                                <button 
                                    type="submit" 
                                    class="px-6 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition duration-200 flex items-center justify-center"
                                >
                                    <i class="fas fa-search mr-2"></i>
                                    搜索
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 我的借阅/热门图书 -->
                <?php if ($role === 'user'): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-purple-500 px-6 py-4">
                        <h3 class="text-lg font-semibold text-white flex items-center">
                            <i class="fas fa-bookmark mr-2"></i>
                            我的借阅记录
                        </h3>
                    </div>
                    <div class="p-6">
                        <?php if (isset($user_borrows) && $user_borrows->num_rows > 0): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full table-auto">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">图书</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">作者</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">借阅日期</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">状态</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php while ($borrow = $user_borrows->fetch_assoc()): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($borrow['title']); ?></div>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-600"><?php echo htmlspecialchars($borrow['author']); ?></div>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-600"><?php echo date('Y-m-d', strtotime($borrow['borrow_date'])); ?></div>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $borrow['status'] === '借出中' ? 'bg-orange-100 text-orange-800' : 'bg-green-100 text-green-800'; ?>">
                                                    <?php echo $borrow['status'] === '借出中' ? '借阅中' : '已归还'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <i class="fas fa-book-open text-gray-300 text-4xl mb-4"></i>
                                <p class="text-gray-500">暂无借阅记录</p>
                                <a href="search.php" class="inline-flex items-center mt-4 px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700">
                                    立即借阅
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- 热门图书 -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-orange-500 px-6 py-4">
                        <h3 class="text-lg font-semibold text-white flex items-center">
                            <i class="fas fa-fire mr-2"></i>
                            热门图书
                        </h3>
                    </div>
                    <div class="p-6">
                        <?php if (isset($popular_books) && $popular_books->num_rows > 0): ?>
                            <div class="space-y-4">
                                <?php $rank = 1; while ($book = $popular_books->fetch_assoc()): ?>
                                <div class="flex items-center p-4 border border-gray-200 rounded-lg hover:shadow-md transition duration-200">
                                    <div class="flex-shrink-0 w-8 h-8 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center font-bold text-sm mr-4">
                                        <?php echo $rank++; ?>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($book['title']); ?></h4>
                                        <p class="text-xs text-gray-600 mt-1"><?php echo htmlspecialchars($book['author']); ?></p>
                                    </div>
                                    <div class="flex items-center text-sm text-gray-500">
                                        <i class="fas fa-users mr-1"></i>
                                        <?php echo $book['borrow_count']; ?> 次借阅
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <i class="fas fa-chart-bar text-gray-300 text-4xl mb-4"></i>
                                <p class="text-gray-500">暂无热门图书数据</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>