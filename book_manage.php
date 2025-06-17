<?php
session_start();
require_once 'db.php';

// 权限验证
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$success_message = '';
$error_message = '';

// 处理新增/编辑图书
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $book_id = $_POST['book_id'] ?? null;
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $isbn = trim($_POST['isbn']);
    $category = trim($_POST['category']);
    $total = (int)$_POST['total'];

    // 数据验证
    if (empty($title) || empty($author) || empty($isbn) || empty($category) || $total < 1) {
        $error_message = "请填写所有必填字段，总数量必须大于0";
    } else {
        try {
            if ($book_id) {
                // 更新图书 - 保持available数量不变
                $stmt = $conn->prepare("UPDATE books SET title=?, author=?, isbn=?, category=?, total=? WHERE id=?");
                $stmt->bind_param("ssssii", $title, $author, $isbn, $category, $total, $book_id);
                $action = "更新";
            } else {
                // 检查ISBN是否已存在
                $check_stmt = $conn->prepare("SELECT id FROM books WHERE isbn = ?");
                $check_stmt->bind_param("s", $isbn);
                $check_stmt->execute();
                if ($check_stmt->get_result()->num_rows > 0) {
                    $error_message = "ISBN号已存在，请检查后重新输入";
                } else {
                    // 新增图书
                    $stmt = $conn->prepare("INSERT INTO books (title, author, isbn, category, total, available) VALUES (?, ?, ?, ?, ?, ?)");
                    $available = $total;
                    $stmt->bind_param("ssssii", $title, $author, $isbn, $category, $total, $available);
                    $action = "添加";
                }
            }

            if (empty($error_message) && $stmt->execute()) {
                $success_message = "图书{$action}成功！";
                // 清空编辑状态
                if (isset($_GET['edit'])) {
                    header("Location: book_manage.php");
                    exit();
                }
            } else if (empty($error_message)) {
                $error_message = "图书{$action}失败：" . $conn->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $error_message = "操作失败：" . $e->getMessage();
        }
    }
}

// 处理删除请求
if (isset($_GET['delete'])) {
    try {
        // 检查是否有借阅记录
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM borrow_records WHERE book_id = ? AND status = '借出中'");
        $check_stmt->bind_param("i", $_GET['delete']);
        $check_stmt->execute();
        $has_borrows = $check_stmt->get_result()->fetch_assoc()['count'] > 0;
        
        if ($has_borrows) {
            $error_message = "该图书还有未归还的借阅记录，无法删除！";
        } else {
            // 删除图书
            $stmt = $conn->prepare("DELETE FROM books WHERE id=?");
            $stmt->bind_param("i", $_GET['delete']);
            if ($stmt->execute()) {
                $success_message = "图书删除成功！";
            } else {
                $error_message = "图书删除失败：" . $conn->error;
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        $error_message = "删除失败：" . $e->getMessage();
    }
}

// 获取统计数据
$stats = [];
try {
    // 总图书数
    $stmt = $conn->prepare("SELECT COUNT(*) as total_books FROM books");
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_books'] = $result->fetch_assoc()['total_books'];

    // 总库存
    $stmt = $conn->prepare("SELECT SUM(total) as total_stock FROM books");
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_stock'] = $result->fetch_assoc()['total_stock'] ?? 0;

    // 可借阅数量
    $stmt = $conn->prepare("SELECT SUM(available) as available_stock FROM books");
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['available_stock'] = $result->fetch_assoc()['available_stock'] ?? 0;

    // 分类数量
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT category) as categories FROM books");
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['categories'] = $result->fetch_assoc()['categories'];

} catch (Exception $e) {
    $error_message = "统计数据加载失败";
}

// 搜索参数
$search_keyword = $_GET['keyword'] ?? '';
$search_author = $_GET['author'] ?? '';
$search_category = $_GET['category'] ?? '';
$search_isbn = $_GET['isbn'] ?? '';

$where = [];
$params = [];

if (!empty($search_keyword)) {
    $where[] = "(title LIKE ? OR author LIKE ? OR isbn LIKE ?)";
    $params[] = "%$search_keyword%";
    $params[] = "%$search_keyword%";
    $params[] = "%$search_keyword%";
}
if (!empty($search_author)) {
    $where[] = "author LIKE ?";
    $params[] = "%$search_author%";
}
if (!empty($search_category)) {
    $where[] = "category = ?";
    $params[] = $search_category;
}
if (!empty($search_isbn)) {
    $where[] = "isbn LIKE ?";
    $params[] = "%$search_isbn%";
}

$sql = "SELECT * FROM books";
if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY id DESC";

// 分页参数
$per_page = 10;
$page = $_GET['page'] ?? 1;
$offset = ($page - 1) * $per_page;

// 获取总数
$count_sql = "SELECT COUNT(*) AS total FROM books" . (!empty($where) ? " WHERE " . implode(' AND ', $where) : '');
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total / $per_page);

// 获取分页数据
$sql .= " LIMIT ?,?";
$params[] = $offset;
$params[] = $per_page;
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $types = str_repeat('s', count($params)-2) . 'ii';
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$books = $stmt->get_result();

// 获取所有分类用于筛选
$categories_stmt = $conn->prepare("SELECT DISTINCT category FROM books ORDER BY category");
$categories_stmt->execute();
$categories = $categories_stmt->get_result();
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
                        <h1 class="text-2xl font-bold">图书管理</h1>
                        <p class="text-green-200 text-sm mt-1">添加、编辑和管理图书馆藏书</p>
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
            <div class="flex space-x-8 py-4">
                <a href="user_manage.php" class="text-gray-500 hover:text-gray-700 hover:border-gray-300 border-b-2 border-transparent pb-2 px-1 text-sm font-medium transition duration-200">
                    <i class="fas fa-user-cog mr-2"></i>用户管理
                </a>
                <a href="book_manage.php" class="text-green-600 border-b-2 border-green-600 pb-2 px-1 text-sm font-medium">
                    <i class="fas fa-book mr-2"></i>图书管理
                </a>
                <a href="borrow_manage.php" class="text-gray-500 hover:text-gray-700 hover:border-gray-300 border-b-2 border-transparent pb-2 px-1 text-sm font-medium transition duration-200">
                    <i class="fas fa-clipboard-list mr-2"></i>借阅管理
                </a>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- 统计卡片 -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
                <div class="flex items-center">
                    <i class="fas fa-book text-blue-500 text-3xl mr-4"></i>
                    <div>
                        <p class="text-gray-600 text-sm font-medium">图书种类</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $stats['total_books'] ?? 0; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
                <div class="flex items-center">
                    <i class="fas fa-warehouse text-green-500 text-3xl mr-4"></i>
                    <div>
                        <p class="text-gray-600 text-sm font-medium">总库存</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $stats['total_stock'] ?? 0; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-orange-500">
                <div class="flex items-center">
                    <i class="fas fa-hand-holding text-orange-500 text-3xl mr-4"></i>
                    <div>
                        <p class="text-gray-600 text-sm font-medium">可借阅</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $stats['available_stock'] ?? 0; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-purple-500">
                <div class="flex items-center">
                    <i class="fas fa-tags text-purple-500 text-3xl mr-4"></i>
                    <div>
                        <p class="text-gray-600 text-sm font-medium">分类数</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $stats['categories'] ?? 0; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 消息提示 -->
        <?php if (!empty($success_message)): ?>
            <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg flex items-center">
                <i class="fas fa-check-circle mr-3"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg flex items-center">
                <i class="fas fa-exclamation-circle mr-3"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- 搜索表单 -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-search mr-2"></i>
                    图书搜索
                </h3>
            </div>
            <div class="p-6">
                <form method="GET" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">关键词搜索</label>
                            <input 
                                type="text" 
                                name="keyword" 
                                value="<?php echo htmlspecialchars($search_keyword); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                placeholder="书名、作者或ISBN"
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">作者</label>
                            <input 
                                type="text" 
                                name="author" 
                                value="<?php echo htmlspecialchars($search_author); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                placeholder="输入作者名"
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">分类</label>
                            <select 
                                name="category" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="">所有分类</option>
                                <?php 
                                $categories->data_seek(0);
                                while ($cat = $categories->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                            <?php echo $search_category === $cat['category'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['category']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">ISBN</label>
                            <input 
                                type="text" 
                                name="isbn" 
                                value="<?php echo htmlspecialchars($search_isbn); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                placeholder="输入ISBN"
                            >
                        </div>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row gap-3">
                        <button 
                            type="submit" 
                            class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition duration-200 flex items-center justify-center"
                        >
                            <i class="fas fa-search mr-2"></i>
                            搜索图书
                        </button>
                        <a 
                            href="book_manage.php" 
                            class="px-6 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 transition duration-200 flex items-center justify-center"
                        >
                            <i class="fas fa-times mr-2"></i>
                            清除搜索
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- 新增/编辑表单 -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas <?php echo isset($_GET['edit']) ? 'fa-edit' : 'fa-plus'; ?> mr-2"></i>
                    <?php echo isset($_GET['edit']) ? '编辑图书' : '新增图书'; ?>
                </h3>
            </div>
            <div class="p-6">
                <?php 
                $edit_book = null;
                if (isset($_GET['edit'])): 
                    $edit_stmt = $conn->prepare("SELECT * FROM books WHERE id = ?");
                    $edit_stmt->bind_param("i", $_GET['edit']);
                    $edit_stmt->execute();
                    $edit_book = $edit_stmt->get_result()->fetch_assoc();
                endif; 
                ?>
                
                <form method="POST" class="space-y-6">
                    <?php if (isset($_GET['edit'])): ?>
                        <input type="hidden" name="book_id" value="<?php echo $edit_book['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-book mr-1"></i>书名 *
                            </label>
                            <input 
                                type="text" 
                                name="title" 
                                required
                                value="<?php echo htmlspecialchars($edit_book['title'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500" 
                                placeholder="请输入书名"
                            >
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-user-edit mr-1"></i>作者 *
                            </label>
                            <input 
                                type="text" 
                                name="author" 
                                required
                                value="<?php echo htmlspecialchars($edit_book['author'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500" 
                                placeholder="请输入作者"
                            >
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-barcode mr-1"></i>ISBN *
                            </label>
                            <input 
                                type="text" 
                                name="isbn" 
                                required
                                value="<?php echo htmlspecialchars($edit_book['isbn'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500" 
                                placeholder="请输入ISBN号"
                            >
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-tag mr-1"></i>分类 *
                            </label>
                            <input 
                                type="text" 
                                name="category" 
                                required
                                value="<?php echo htmlspecialchars($edit_book['category'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500" 
                                placeholder="请输入分类"
                            >
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-sort-numeric-up mr-1"></i>总数量 *
                            </label>
                            <input 
                                type="number" 
                                name="total" 
                                min="1" 
                                required
                                value="<?php echo $edit_book['total'] ?? ''; ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500" 
                                placeholder="请输入总数量"
                            >
                        </div>
                        
                        <?php if (isset($_GET['edit']) && $edit_book): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-hand-holding mr-1"></i>当前可借数量
                            </label>
                            <input 
                                type="text" 
                                value="<?php echo $edit_book['available']; ?>" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100" 
                                readonly
                            >
                            <p class="text-xs text-gray-500 mt-1">可借数量由系统自动管理</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row gap-3">
                        <button 
                            type="submit" 
                            class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 transition duration-200 flex items-center justify-center"
                        >
                            <i class="fas fa-save mr-2"></i>
                            <?php echo isset($_GET['edit']) ? '更新图书' : '添加图书'; ?>
                        </button>
                        
                        <?php if (isset($_GET['edit'])): ?>
                            <a 
                                href="book_manage.php" 
                                class="px-6 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 transition duration-200 flex items-center justify-center"
                            >
                                <i class="fas fa-times mr-2"></i>
                                取消编辑
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- 图书列表 -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-list mr-2"></i>
                        图书列表
                    </h3>
                    <p class="text-sm text-gray-600">
                        共 <?php echo $total; ?> 本图书
                    </p>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-id-badge mr-1"></i>ID
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-book mr-1"></i>书名
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-user-edit mr-1"></i>作者
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-barcode mr-1"></i>ISBN
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-tag mr-1"></i>分类
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-sort-numeric-up mr-1"></i>总数
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-hand-holding mr-1"></i>可借
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-cogs mr-1"></i>操作
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if ($books->num_rows > 0): ?>
                            <?php while ($book = $books->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition duration-200">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        #<?php echo $book['id']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($book['title']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <?php echo htmlspecialchars($book['author']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <?php echo htmlspecialchars($book['isbn']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <?php echo htmlspecialchars($book['category']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $book['total']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $book['available'] > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo $book['available']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                        <a 
                                            href="book_manage.php?edit=<?php echo $book['id']; ?>" 
                                            class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 transition duration-200"
                                        >
                                            <i class="fas fa-edit mr-1"></i>
                                            编辑
                                        </a>
                                        
                                        <button 
                                            onclick="confirmDelete(<?php echo $book['id']; ?>, '<?php echo htmlspecialchars($book['title']); ?>')"
                                            class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition duration-200"
                                        >
                                            <i class="fas fa-trash mr-1"></i>
                                            删除
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center">
                                    <div class="text-gray-500">
                                        <i class="fas fa-book text-4xl mb-4"></i>
                                        <p class="text-lg">暂无图书数据</p>
                                        <?php if (!empty($search_keyword) || !empty($search_author) || !empty($search_category) || !empty($search_isbn)): ?>
                                            <p class="text-sm mt-2">没有找到符合条件的图书</p>
                                            <a href="book_manage.php" class="text-blue-600 hover:text-blue-500 text-sm">清除搜索条件</a>
                                        <?php else: ?>
                                            <p class="text-sm mt-2">开始添加您的第一本图书吧</p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- 分页 -->
            <?php if ($total_pages > 1): ?>
                <div class="bg-white px-6 py-4 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            显示第 <?php echo $offset + 1; ?> - <?php echo min($offset + $per_page, $total); ?> 条，共 <?php echo $total; ?> 条记录
                        </div>
                        <div class="flex space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                                   class="px-3 py-2 text-sm bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition duration-200">
                                    <i class="fas fa-chevron-left mr-1"></i>上一页
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="px-3 py-2 text-sm rounded-md transition duration-200 <?php echo $i === (int)$page ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                                   class="px-3 py-2 text-sm bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition duration-200">
                                    下一页<i class="fas fa-chevron-right ml-1"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 删除确认模态框 -->
    <div id="deleteModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"></div>
            
            <div class="inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-xl rounded-lg">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-exclamation-triangle mr-2 text-red-600"></i>
                        删除图书确认
                    </h3>
                    <button onclick="closeDeleteModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="mb-6">
                    <div class="bg-red-50 border border-red-200 rounded-md p-4 mb-4">
                        <p class="text-sm text-red-800">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            警告：此操作不可逆！
                        </p>
                    </div>
                    <p class="text-sm text-gray-600">
                        您确定要删除图书 "<strong id="deleteBookTitle"></strong>" 吗？
                    </p>
                    <p class="text-xs text-gray-500 mt-2">
                        删除后该图书的所有信息将被永久清除。
                    </p>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button 
                        onclick="closeDeleteModal()"
                        class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500"
                    >
                        取消
                    </button>
                    <a 
                        id="deleteConfirmLink"
                        href="#"
                        class="px-4 py-2 text-sm text-white bg-red-600 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                    >
                        确认删除
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 删除确认功能
        function confirmDelete(bookId, bookTitle) {
            document.getElementById('deleteBookTitle').textContent = bookTitle;
            document.getElementById('deleteConfirmLink').href = 'book_manage.php?delete=' + bookId;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // 点击背景关闭模态框
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        // ESC键关闭模态框
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDeleteModal();
            }
        });

        // 表单验证
        document.querySelector('form').addEventListener('submit', function(e) {
            const title = document.querySelector('input[name="title"]').value.trim();
            const author = document.querySelector('input[name="author"]').value.trim();
            const isbn = document.querySelector('input[name="isbn"]').value.trim();
            const category = document.querySelector('input[name="category"]').value.trim();
            const total = document.querySelector('input[name="total"]').value;

            if (!title || !author || !isbn || !category || !total || total < 1) {
                e.preventDefault();
                alert('请填写所有必填字段，总数量必须大于0');
                return false;
            }

            // ISBN格式验证（简单验证）
            if (isbn.length < 10 || isbn.length > 17) {
                e.preventDefault();
                alert('ISBN号格式不正确');
                return false;
            }
        });

        // 自动清除提示消息
        setTimeout(function() {
            const alerts = document.querySelectorAll('.bg-green-100, .bg-red-100');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>