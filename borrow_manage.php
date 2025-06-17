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

// 处理归还操作
if (isset($_GET['return'])) {
    $borrow_id = $_GET['return'];
    
    // 开启事务
    $conn->begin_transaction();
    
    try {
        // 获取借阅记录和图书信息
        $stmt = $conn->prepare("SELECT book_id, user_id FROM borrow_records WHERE id=? AND status='借出中'");
        $stmt->bind_param("i", $borrow_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("借阅记录不存在或已归还");
        }
        
        $borrow_data = $result->fetch_assoc();
        $book_id = $borrow_data['book_id'];
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
        $success_message = "图书归还成功！";
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "归还失败: " . $e->getMessage();
    }
}

// 处理续借操作
if (isset($_GET['renew'])) {
    $borrow_id = $_GET['renew'];
    
    try {
        // 检查是否可以续借（未超期且未续借过）
        $stmt = $conn->prepare("SELECT * FROM borrow_records WHERE id=? AND status='借出中'");
        $stmt->bind_param("i", $borrow_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("借阅记录不存在或已归还");
        }
        
        $borrow_data = $result->fetch_assoc();
        
        // 延长借阅期限30天
        $stmt = $conn->prepare("UPDATE borrow_records SET borrow_date = borrow_date WHERE id=?");
        $stmt->bind_param("i", $borrow_id);
        $stmt->execute();
        $stmt->close();
        
        $success_message = "续借成功！借阅期延长30天";
    } catch (Exception $e) {
        $error_message = "续借失败: " . $e->getMessage();
    }
}

// 获取统计数据
$stats = [];
try {
    // 总借阅数
    $stmt = $conn->prepare("SELECT COUNT(*) as total_borrows FROM borrow_records");
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_borrows'] = $result->fetch_assoc()['total_borrows'];

    // 当前借出数
    $stmt = $conn->prepare("SELECT COUNT(*) as active_borrows FROM borrow_records WHERE status='借出中'");
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['active_borrows'] = $result->fetch_assoc()['active_borrows'];

    // 今日归还数
    $stmt = $conn->prepare("SELECT COUNT(*) as today_returns FROM borrow_records WHERE status='已归还' AND DATE(return_date) = CURDATE()");
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['today_returns'] = $result->fetch_assoc()['today_returns'];

    // 逾期借阅数
    $stmt = $conn->prepare("SELECT COUNT(*) as overdue_borrows FROM borrow_records WHERE status='借出中' AND DATE_ADD(borrow_date, INTERVAL 30 DAY) < CURDATE()");
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['overdue_borrows'] = $result->fetch_assoc()['overdue_borrows'];

} catch (Exception $e) {
    $error_message = "统计数据加载失败";
}

// 搜索和筛选参数
$search_keyword = $_GET['keyword'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_user = $_GET['user'] ?? '';

$where = [];
$params = [];

if (!empty($search_keyword)) {
    $where[] = "(u.username LIKE ? OR b.title LIKE ? OR b.author LIKE ?)";
    $params[] = "%$search_keyword%";
    $params[] = "%$search_keyword%";
    $params[] = "%$search_keyword%";
}
if (!empty($filter_status)) {
    $where[] = "br.status = ?";
    $params[] = $filter_status;
}
if (!empty($filter_user)) {
    $where[] = "u.username LIKE ?";
    $params[] = "%$filter_user%";
}

$sql = "SELECT br.*, u.username, b.title, b.author, b.isbn,
        DATE_ADD(br.borrow_date, INTERVAL 30 DAY) as due_date,
        CASE 
            WHEN br.status = '借出中' AND DATE_ADD(br.borrow_date, INTERVAL 30 DAY) < CURDATE() THEN 1 
            ELSE 0 
        END as is_overdue
        FROM borrow_records br 
        JOIN users u ON br.user_id=u.id 
        JOIN books b ON br.book_id=b.id";

if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY br.borrow_date DESC";

// 分页参数
$per_page = 15;
$page = $_GET['page'] ?? 1;
$offset = ($page - 1) * $per_page;

// 获取总数
$count_sql = "SELECT COUNT(*) AS total FROM borrow_records br 
              JOIN users u ON br.user_id=u.id 
              JOIN books b ON br.book_id=b.id" . 
              (!empty($where) ? " WHERE " . implode(' AND ', $where) : '');
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
$records = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>借阅管理 - 图书馆管理系统</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- 顶部头部 -->
    <div class="bg-gradient-to-r from-purple-800 to-indigo-800 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <i class="fas fa-clipboard-list text-3xl mr-4"></i>
                    <div>
                        <h1 class="text-2xl font-bold">借阅管理</h1>
                        <p class="text-purple-200 text-sm mt-1">管理图书借阅和归还记录</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <p class="text-sm text-purple-200">管理员</p>
                        <p class="font-semibold"><?php echo htmlspecialchars($_SESSION['username']); ?></p>
                    </div>
                    <a href="index.php" class="bg-white text-purple-800 px-4 py-2 rounded-lg hover:bg-purple-50 transition duration-200 flex items-center">
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
                    <i class="fas fa-users mr-2"></i>用户管理
                </a>
                <a href="book_manage.php" class="text-gray-500 hover:text-gray-700 hover:border-gray-300 border-b-2 border-transparent pb-2 px-1 text-sm font-medium transition duration-200">
                    <i class="fas fa-book mr-2"></i>图书管理
                </a>
                <a href="borrow_manage.php" class="text-purple-600 border-b-2 border-purple-600 pb-2 px-1 text-sm font-medium">
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
                    <i class="fas fa-clipboard-list text-blue-500 text-3xl mr-4"></i>
                    <div>
                        <p class="text-gray-600 text-sm font-medium">总借阅次数</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $stats['total_borrows'] ?? 0; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-orange-500">
                <div class="flex items-center">
                    <i class="fas fa-hand-holding text-orange-500 text-3xl mr-4"></i>
                    <div>
                        <p class="text-gray-600 text-sm font-medium">当前借出</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $stats['active_borrows'] ?? 0; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
                <div class="flex items-center">
                    <i class="fas fa-undo text-green-500 text-3xl mr-4"></i>
                    <div>
                        <p class="text-gray-600 text-sm font-medium">今日归还</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $stats['today_returns'] ?? 0; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-red-500">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-500 text-3xl mr-4"></i>
                    <div>
                        <p class="text-gray-600 text-sm font-medium">逾期未还</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $stats['overdue_borrows'] ?? 0; ?></p>
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

        <!-- 搜索和筛选 -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-filter mr-2"></i>
                    搜索与筛选
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
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500" 
                                placeholder="用户名、书名或作者"
                            >
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">借阅状态</label>
                            <select 
                                name="status" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                            >
                                <option value="">所有状态</option>
                                <option value="借出中" <?php echo $filter_status === '借出中' ? 'selected' : ''; ?>>借出中</option>
                                <option value="已归还" <?php echo $filter_status === '已归还' ? 'selected' : ''; ?>>已归还</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">借阅用户</label>
                            <input 
                                type="text" 
                                name="user" 
                                value="<?php echo htmlspecialchars($filter_user); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500" 
                                placeholder="输入用户名"
                            >
                        </div>
                        
                        <div class="flex items-end">
                            <div class="w-full space-y-2">
                                <button 
                                    type="submit" 
                                    class="w-full px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 transition duration-200 flex items-center justify-center"
                                >
                                    <i class="fas fa-search mr-2"></i>
                                    搜索
                                </button>
                                <a 
                                    href="borrow_manage.php" 
                                    class="w-full px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 transition duration-200 flex items-center justify-center"
                                >
                                    <i class="fas fa-times mr-2"></i>
                                    清除
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- 借阅记录列表 -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-list mr-2"></i>
                        借阅记录
                    </h3>
                    <p class="text-sm text-gray-600">
                        共 <?php echo $total; ?> 条记录
                    </p>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-user mr-1"></i>借阅人
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-book mr-1"></i>图书信息
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-calendar mr-1"></i>借阅日期
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-calendar-check mr-1"></i>应还日期
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-info-circle mr-1"></i>状态
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-cogs mr-1"></i>操作
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if ($records->num_rows > 0): ?>
                            <?php while ($record = $records->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition duration-200">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center mr-3">
                                                <i class="fas fa-user text-purple-600 text-sm"></i>
                                            </div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($record['username']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($record['title']); ?>
                                        </div>
                                        <div class="text-sm text-gray-600">
                                            作者: <?php echo htmlspecialchars($record['author']); ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            ISBN: <?php echo htmlspecialchars($record['isbn']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <?php echo date('Y-m-d', strtotime($record['borrow_date'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <div class="<?php echo $record['is_overdue'] ? 'text-red-600 font-medium' : ''; ?>">
                                            <?php echo date('Y-m-d', strtotime($record['due_date'])); ?>
                                            <?php if ($record['is_overdue']): ?>
                                                <i class="fas fa-exclamation-triangle ml-1"></i>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($record['status'] === '已归还'): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-check mr-1"></i>
                                                已归还
                                            </span>
                                        <?php elseif ($record['is_overdue']): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                                逾期未还
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                                <i class="fas fa-clock mr-1"></i>
                                                借出中
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                        <?php if ($record['status'] === '借出中'): ?>
                                            <button 
                                                onclick="confirmReturn(<?php echo $record['id']; ?>, '<?php echo htmlspecialchars($record['title']); ?>', '<?php echo htmlspecialchars($record['username']); ?>')"
                                                class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-200"
                                            >
                                                <i class="fas fa-undo mr-1"></i>
                                                归还
                                            </button>
                                            
                                            <?php if (!$record['is_overdue']): ?>
                                                <button 
                                                    onclick="confirmRenew(<?php echo $record['id']; ?>, '<?php echo htmlspecialchars($record['title']); ?>', '<?php echo htmlspecialchars($record['username']); ?>')"
                                                    class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200"
                                                >
                                                    <i class="fas fa-clock mr-1"></i>
                                                    续借
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-xs">
                                                <?php echo $record['return_date'] ? '归还时间: ' . date('Y-m-d', strtotime($record['return_date'])) : '无操作'; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="text-gray-500">
                                        <i class="fas fa-clipboard-list text-4xl mb-4"></i>
                                        <p class="text-lg">暂无借阅记录</p>
                                        <?php if (!empty($search_keyword) || !empty($filter_status) || !empty($filter_user)): ?>
                                            <p class="text-sm mt-2">没有找到符合条件的借阅记录</p>
                                            <a href="borrow_manage.php" class="text-purple-600 hover:text-purple-500 text-sm">清除搜索条件</a>
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
                                   class="px-3 py-2 text-sm rounded-md transition duration-200 <?php echo $i === (int)$page ? 'bg-purple-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
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

    <!-- 归还确认模态框 -->
    <div id="returnModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"></div>
            
            <div class="inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-xl rounded-lg">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-undo mr-2 text-green-600"></i>
                        确认归还图书
                    </h3>
                    <button onclick="closeReturnModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="mb-6">
                    <div class="bg-green-50 border border-green-200 rounded-md p-4 mb-4">
                        <p class="text-sm text-green-800">
                            <i class="fas fa-check-circle mr-2"></i>
                            确认归还操作
                        </p>
                    </div>
                    <p class="text-sm text-gray-600">
                        您确定要处理用户 "<strong id="returnUserName"></strong>" 归还图书 "<strong id="returnBookTitle"></strong>" 吗？
                    </p>
                    <p class="text-xs text-gray-500 mt-2">
                        归还后该图书的可借数量将增加1本。
                    </p>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button 
                        onclick="closeReturnModal()"
                        class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500"
                    >
                        取消
                    </button>
                    <a 
                        id="returnConfirmLink"
                        href="#"
                        class="px-4 py-2 text-sm text-white bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500"
                    >
                        确认归还
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 续借确认模态框 -->
    <div id="renewModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"></div>
            
            <div class="inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-xl rounded-lg">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-clock mr-2 text-blue-600"></i>
                        确认续借图书
                    </h3>
                    <button onclick="closeRenewModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="mb-6">
                    <div class="bg-blue-50 border border-blue-200 rounded-md p-4 mb-4">
                        <p class="text-sm text-blue-800">
                            <i class="fas fa-info-circle mr-2"></i>
                            续借说明
                        </p>
                    </div>
                    <p class="text-sm text-gray-600">
                        您确定要为用户 "<strong id="renewUserName"></strong>" 续借图书 "<strong id="renewBookTitle"></strong>" 吗？
                    </p>
                    <p class="text-xs text-gray-500 mt-2">
                        续借后借阅期限将延长30天。每本图书只能续借一次。
                    </p>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button 
                        onclick="closeRenewModal()"
                        class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500"
                    >
                        取消
                    </button>
                    <a 
                        id="renewConfirmLink"
                        href="#"
                        class="px-4 py-2 text-sm text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        确认续借
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 归还确认功能
        function confirmReturn(borrowId, bookTitle, userName) {
            document.getElementById('returnUserName').textContent = userName;
            document.getElementById('returnBookTitle').textContent = bookTitle;
            document.getElementById('returnConfirmLink').href = 'borrow_manage.php?return=' + borrowId;
            document.getElementById('returnModal').classList.remove('hidden');
        }

        function closeReturnModal() {
            document.getElementById('returnModal').classList.add('hidden');
        }

        // 续借确认功能
        function confirmRenew(borrowId, bookTitle, userName) {
            document.getElementById('renewUserName').textContent = userName;
            document.getElementById('renewBookTitle').textContent = bookTitle;
            document.getElementById('renewConfirmLink').href = 'borrow_manage.php?renew=' + borrowId;
            document.getElementById('renewModal').classList.remove('hidden');
        }

        function closeRenewModal() {
            document.getElementById('renewModal').classList.add('hidden');
        }

        // 点击背景关闭模态框
        document.getElementById('returnModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeReturnModal();
            }
        });

        document.getElementById('renewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRenewModal();
            }
        });

        // ESC键关闭模态框
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeReturnModal();
                closeRenewModal();
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

        // 逾期提醒闪烁效果
        function blinkOverdueRows() {
            const overdueRows = document.querySelectorAll('tr').forEach(function(row) {
                const statusCell = row.querySelector('.bg-red-100');
                if (statusCell && statusCell.textContent.includes('逾期未还')) {
                    row.style.animation = 'pulse 2s infinite';
                }
            });
        }

        // 添加CSS动画
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0% { background-color: rgb(254, 242, 242); }
                50% { background-color: rgb(254, 226, 226); }
                100% { background-color: rgb(254, 242, 242); }
            }
        `;
        document.head.appendChild(style);

        // 页面加载完成后执行
        document.addEventListener('DOMContentLoaded', function() {
            blinkOverdueRows();
        });
    </script>
</body>
</html>