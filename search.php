<?php
session_start();
require_once 'db.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$success_message = '';
$error_message = '';

// 处理借阅请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'borrow') {
    $book_id = (int)$_POST['book_id'];
    $user_id = $_SESSION['user_id'];
    
    // 开启事务
    $conn->begin_transaction();
    
    try {
        // 检查用户是否已经借阅了此书且未归还
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM borrow_records WHERE user_id = ? AND book_id = ? AND status = '借出中'");
        $check_stmt->bind_param("ii", $user_id, $book_id);
        $check_stmt->execute();
        $already_borrowed = $check_stmt->get_result()->fetch_assoc()['count'] > 0;
        
        if ($already_borrowed) {
            throw new Exception("您已经借阅了这本书，请先归还后再借阅");
        }
        
        // 检查用户当前借阅数量是否超过限制（最多5本）
        $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM borrow_records WHERE user_id = ? AND status = '借出中'");
        $count_stmt->bind_param("i", $user_id);
        $count_stmt->execute();
        $current_borrows = $count_stmt->get_result()->fetch_assoc()['count'];
        
        if ($current_borrows >= 5) {
            throw new Exception("您当前借阅的图书数量已达上限（5本），请先归还部分图书");
        }
        
        // 检查图书是否可借
        $book_stmt = $conn->prepare("SELECT title, available FROM books WHERE id = ?");
        $book_stmt->bind_param("i", $book_id);
        $book_stmt->execute();
        $book_result = $book_stmt->get_result();
        
        if ($book_result->num_rows === 0) {
            throw new Exception("图书不存在");
        }
        
        $book_data = $book_result->fetch_assoc();
        if ($book_data['available'] <= 0) {
            throw new Exception("该图书暂无库存，无法借阅");
        }
        
        // 创建借阅记录
        $borrow_stmt = $conn->prepare("INSERT INTO borrow_records (user_id, book_id, borrow_date, status) VALUES (?, ?, CURDATE(), '借出中')");
        $borrow_stmt->bind_param("ii", $user_id, $book_id);
        $borrow_stmt->execute();
        
        // 减少图书可借数量
        $update_stmt = $conn->prepare("UPDATE books SET available = available - 1 WHERE id = ?");
        $update_stmt->bind_param("i", $book_id);
        $update_stmt->execute();
        
        $conn->commit();
        $success_message = "《{$book_data['title']}》借阅成功！请在30天内归还。";
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
    }
}

// 获取搜索参数
$keyword = $_GET['keyword'] ?? '';

// 构建搜索条件
$where = ["available > 0"]; // 只显示有库存的图书
$params = [];

if (!empty($keyword)) {
    $where[] = "(title LIKE ? OR author LIKE ? OR isbn LIKE ?)";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
}

$sql = "SELECT * FROM books WHERE " . implode(' AND ', $where) . " ORDER BY id DESC";

// 分页参数
$per_page = 12;
$page = $_GET['page'] ?? 1;
$offset = ($page - 1) * $per_page;

// 获取总数
$count_sql = "SELECT COUNT(*) AS total FROM books WHERE " . implode(' AND ', $where);
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

// 获取用户当前借阅情况
$user_borrows_stmt = $conn->prepare("SELECT COUNT(*) as count FROM borrow_records WHERE user_id = ? AND status = '借出中'");
$user_borrows_stmt->bind_param("i", $_SESSION['user_id']);
$user_borrows_stmt->execute();
$user_current_borrows = $user_borrows_stmt->get_result()->fetch_assoc()['count'];

// 获取统计数据
$stats = [];
try {
    // 总图书数
    $stmt = $conn->prepare("SELECT COUNT(*) as total_books FROM books WHERE available > 0");
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_books'] = $result->fetch_assoc()['total_books'];

    // 用户借阅历史
    $stmt = $conn->prepare("SELECT COUNT(*) as total_borrowed FROM borrow_records WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['user_total_borrowed'] = $result->fetch_assoc()['total_borrowed'];

} catch (Exception $e) {
    // 忽略统计错误
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>搜索图书 - 图书馆管理系统</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.5s ease-in-out',
                        'bounce-gentle': 'bounceGentle 2s infinite',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' }
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(20px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' }
                        },
                        bounceGentle: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-5px)' }
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gradient-to-br from-blue-50 via-white to-purple-50 min-h-screen">
    <!-- 顶部导航 -->
    <nav class="bg-white/80 backdrop-blur-md shadow-lg border-b border-gray-200 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <a href="index.php" class="flex items-center group">
                        <div class="relative">
                            <i class="fas fa-book text-blue-600 text-2xl mr-3 group-hover:text-blue-700 transition-colors duration-200"></i>
                            <div class="absolute -top-1 -right-1 w-2 h-2 bg-blue-400 rounded-full animate-pulse"></div>
                        </div>
                        <span class="text-xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">图书馆</span>
                    </a>
                </div>
                <div class="flex items-center space-x-6">
                    <a href="index.php" class="text-gray-600 hover:text-blue-600 transition duration-200 flex items-center group">
                        <i class="fas fa-home mr-1 group-hover:scale-110 transition-transform duration-200"></i>首页
                    </a>
                    <div class="flex items-center text-sm text-gray-600 bg-gray-50 rounded-full px-3 py-2">
                        <i class="fas fa-user mr-2 text-blue-500"></i>
                        <span class="font-medium"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        <span class="ml-2 text-xs bg-gradient-to-r from-blue-500 to-purple-500 text-white px-2 py-1 rounded-full">
                            已借: <?php echo $user_current_borrows; ?>/5
                        </span>
                    </div>
                    <a href="logout.php" class="text-gray-600 hover:text-red-600 transition duration-200 flex items-center group">
                        <i class="fas fa-sign-out-alt mr-1 group-hover:scale-110 transition-transform duration-200"></i>退出
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- 页面头部 -->
    <div class="bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-600 relative overflow-hidden">
        <div class="absolute inset-0">
            <div class="absolute inset-0 bg-gradient-to-r from-blue-600/90 to-purple-600/90"></div>
            <div class="absolute top-0 left-0 w-full h-full">
                <div class="absolute top-10 left-10 w-20 h-20 bg-white/10 rounded-full animate-bounce-gentle"></div>
                <div class="absolute top-20 right-20 w-16 h-16 bg-white/10 rounded-full animate-bounce-gentle" style="animation-delay: 1s;"></div>
                <div class="absolute bottom-10 left-1/3 w-12 h-12 bg-white/10 rounded-full animate-bounce-gentle" style="animation-delay: 0.5s;"></div>
            </div>
        </div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="text-center">
                <h1 class="text-4xl md:text-5xl font-bold text-white mb-4 animate-fade-in">
                    发现知识的宝藏
                </h1>
                <p class="text-xl text-blue-100 mb-8 animate-slide-up">
                    在浩瀚的书海中找到您心仪的图书
                </p>
                
                <!-- 统计信息 -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-3xl mx-auto animate-slide-up">
                    <div class="bg-white/20 backdrop-blur-sm rounded-2xl p-6 border border-white/30">
                        <div class="text-3xl font-bold text-white"><?php echo $stats['total_books'] ?? 0; ?></div>
                        <div class="text-blue-100 text-sm mt-1">可借阅图书</div>
                    </div>
                    <div class="bg-white/20 backdrop-blur-sm rounded-2xl p-6 border border-white/30">
                        <div class="text-3xl font-bold text-white"><?php echo $user_current_borrows; ?></div>
                        <div class="text-blue-100 text-sm mt-1">当前借阅</div>
                    </div>
                    <div class="bg-white/20 backdrop-blur-sm rounded-2xl p-6 border border-white/30">
                        <div class="text-3xl font-bold text-white"><?php echo $stats['user_total_borrowed'] ?? 0; ?></div>
                        <div class="text-blue-100 text-sm mt-1">累计借阅</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- 搜索表单 -->
        <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-xl p-8 mb-8 border border-gray-200 hover:shadow-2xl transition-all duration-300">
            <div class="text-center mb-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-2">搜索您感兴趣的图书</h2>
                <p class="text-gray-600">输入关键词开始您的阅读之旅</p>
            </div>
            <form method="GET" class="max-w-2xl mx-auto">
                <div class="relative">
                    <input 
                        type="text" 
                        name="keyword" 
                        value="<?php echo htmlspecialchars($keyword); ?>"
                        class="w-full px-6 py-4 text-lg border-2 border-gray-200 rounded-2xl focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-300 pl-14 pr-32 shadow-lg" 
                        placeholder="输入书名、作者或ISBN..."
                    >
                    <i class="fas fa-search absolute left-5 top-1/2 transform -translate-y-1/2 text-gray-400 text-lg"></i>
                    <button 
                        type="submit" 
                        class="absolute right-3 top-1/2 transform -translate-y-1/2 px-6 py-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-xl hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-4 focus:ring-blue-500/20 transition-all duration-300 font-medium shadow-lg hover:shadow-xl"
                    >
                        <i class="fas fa-search mr-2"></i>
                        搜索
                    </button>
                </div>
            </form>
        </div>

        <!-- 消息提示 -->
        <?php if (!empty($success_message)): ?>
            <div class="mb-6 bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 text-green-800 px-6 py-4 rounded-2xl flex items-center shadow-lg animate-slide-up">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-2xl text-green-500 mr-4"></i>
                </div>
                <div>
                    <div class="font-medium"><?php echo $success_message; ?></div>
                    <div class="text-sm text-green-600 mt-1">您可以在个人中心查看借阅记录</div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="mb-6 bg-gradient-to-r from-red-50 to-pink-50 border border-red-200 text-red-800 px-6 py-4 rounded-2xl flex items-center shadow-lg animate-slide-up">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-2xl text-red-500 mr-4"></i>
                </div>
                <div>
                    <div class="font-medium"><?php echo $error_message; ?></div>
                    <div class="text-sm text-red-600 mt-1">请检查借阅限制或联系管理员</div>
                </div>
            </div>
        <?php endif; ?>

        <!-- 搜索结果头部 -->
        <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-lg overflow-hidden mb-8 border border-gray-200">
            <div class="px-8 py-6 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-books mr-3 text-blue-600"></i>
                            搜索结果
                            <?php if (!empty($keyword)): ?>
                                <span class="text-lg font-normal text-gray-600 ml-2">- "<?php echo htmlspecialchars($keyword); ?>"</span>
                            <?php endif; ?>
                        </h2>
                        <p class="text-gray-600 mt-1 flex items-center">
                            <i class="fas fa-info-circle mr-2 text-blue-500"></i>
                            共找到 <span class="font-semibold text-blue-600 mx-1"><?php echo $total; ?></span> 本图书
                        </p>
                    </div>
                    <?php if (!empty($keyword)): ?>
                        <a href="search.php" class="mt-4 sm:mt-0 inline-flex items-center px-4 py-2 bg-gray-500 text-white rounded-xl hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-times mr-2"></i>
                            清除搜索
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 图书列表 -->
        <?php if ($books->num_rows > 0): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                <?php $index = 0; while ($book = $books->fetch_assoc()): ?>
                    <div class="group bg-white/70 backdrop-blur-sm rounded-2xl shadow-lg overflow-hidden hover:shadow-2xl transition-all duration-500 border border-gray-200 hover:border-blue-300 book-card" style="animation-delay: <?php echo $index * 0.1; ?>s;">
                        <!-- 图书封面 -->
                        <div class="relative h-48 bg-gradient-to-br from-blue-400 via-purple-500 to-indigo-600 overflow-hidden">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent"></div>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <i class="fas fa-book text-white text-4xl group-hover:scale-110 transition-transform duration-300"></i>
                            </div>
                            <!-- 库存标识 -->
                            <div class="absolute top-4 right-4">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?php echo $book['available'] > 5 ? 'bg-green-100 text-green-800' : ($book['available'] > 0 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                    <i class="fas fa-circle mr-1 text-xs"></i>
                                    <?php echo $book['available'] > 5 ? '充足' : ($book['available'] > 0 ? '紧张' : '缺货'); ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- 图书信息 -->
                        <div class="p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-3 line-clamp-2 group-hover:text-blue-600 transition-colors duration-200">
                                <?php echo htmlspecialchars($book['title']); ?>
                            </h3>
                            
                            <div class="space-y-2 text-sm text-gray-600 mb-6">
                                <div class="flex items-center">
                                    <i class="fas fa-user mr-3 text-blue-500 w-4"></i>
                                    <span class="flex-1 truncate"><?php echo htmlspecialchars($book['author']); ?></span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-tag mr-3 text-purple-500 w-4"></i>
                                    <span class="flex-1 truncate"><?php echo htmlspecialchars($book['category']); ?></span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-barcode mr-3 text-gray-500 w-4"></i>
                                    <span class="flex-1 truncate font-mono text-xs"><?php echo htmlspecialchars($book['isbn']); ?></span>
                                </div>
                                <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                                    <div class="flex items-center">
                                        <i class="fas fa-warehouse mr-2 text-gray-500"></i>
                                        <span>总数: <span class="font-semibold"><?php echo $book['total']; ?></span></span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-hand-holding mr-2 <?php echo $book['available'] > 0 ? 'text-green-500' : 'text-red-500'; ?>"></i>
                                        <span class="<?php echo $book['available'] > 0 ? 'text-green-600' : 'text-red-600'; ?> font-semibold">
                                            可借: <?php echo $book['available']; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 借阅按钮 -->
                            <?php if ($book['available'] > 0): ?>
                                <button 
                                    onclick="confirmBorrow(<?php echo $book['id']; ?>, '<?php echo htmlspecialchars($book['title'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($book['author'], ENT_QUOTES); ?>')"
                                    class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white py-3 px-4 rounded-xl hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-4 focus:ring-blue-500/20 transition-all duration-300 font-medium shadow-lg hover:shadow-xl group-hover:scale-105"
                                    type="button"
                                >
                                    <i class="fas fa-plus mr-2"></i>
                                    立即借阅
                                </button>
                            <?php else: ?>
                                <button 
                                    disabled
                                    class="w-full bg-gray-400 text-white py-3 px-4 rounded-xl cursor-not-allowed font-medium opacity-60"
                                    type="button"
                                >
                                    <i class="fas fa-times mr-2"></i>
                                    暂无库存
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php $index++; endwhile; ?>
            </div>

            <!-- 分页 -->
            <?php if ($total_pages > 1): ?>
                <div class="mt-12 flex justify-center">
                    <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-lg border border-gray-200 p-2">
                        <div class="flex items-center space-x-1">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                                   class="px-4 py-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition-all duration-200 flex items-center">
                                    <i class="fas fa-chevron-left mr-2"></i>上一页
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="px-4 py-2 rounded-xl transition-all duration-200 <?php echo $i === (int)$page ? 'bg-gradient-to-r from-blue-600 to-purple-600 text-white shadow-lg' : 'text-gray-600 hover:text-blue-600 hover:bg-blue-50'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                                   class="px-4 py-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition-all duration-200 flex items-center">
                                    下一页<i class="fas fa-chevron-right ml-2"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- 空状态 -->
            <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-lg p-16 text-center border border-gray-200">
                <div class="max-w-md mx-auto">
                    <div class="mb-6">
                        <i class="fas fa-search text-gray-300 text-8xl mb-4 animate-bounce-gentle"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">没有找到符合条件的图书</h3>
                    <p class="text-gray-600 mb-8">
                        <?php if (!empty($keyword)): ?>
                            很抱歉，没有找到与 "<span class="font-semibold text-blue-600"><?php echo htmlspecialchars($keyword); ?></span>" 相关的图书
                        <?php else: ?>
                            请输入关键词开始搜索
                        <?php endif; ?>
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="search.php" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-xl hover:from-blue-700 hover:to-purple-700 transition-all duration-300 shadow-lg hover:shadow-xl">
                            <i class="fas fa-refresh mr-2"></i>
                            重新搜索
                        </a>
                        <a href="index.php" class="inline-flex items-center px-6 py-3 bg-gray-500 text-white rounded-xl hover:bg-gray-600 transition-all duration-300">
                            <i class="fas fa-home mr-2"></i>
                            返回首页
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- 借阅确认模态框 -->
    <div id="borrowModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-black/50 backdrop-blur-sm"></div>
            
            <div class="inline-block w-full max-w-lg p-0 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-3xl border border-gray-200">
                <!-- 模态框头部 -->
                <div class="bg-gradient-to-r from-blue-600 to-purple-600 px-8 py-6 text-white">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-bold flex items-center">
                            <i class="fas fa-book mr-3 text-2xl"></i>
                            确认借阅图书
                        </h3>
                        <button onclick="closeBorrowModal()" class="text-white/80 hover:text-white transition-colors duration-200 p-2 hover:bg-white/20 rounded-full">
                            <i class="fas fa-times text-lg"></i>
                        </button>
                    </div>
                </div>
                
                <form method="POST" id="borrowForm">
                    <input type="hidden" name="action" value="borrow">
                    <input type="hidden" name="book_id" id="borrowBookId">
                    
                    <div class="p-8">
                        <!-- 借阅须知 -->
                        <div class="bg-gradient-to-r from-blue-50 to-purple-50 border border-blue-200 rounded-2xl p-6 mb-6">
                            <div class="flex items-center mb-4">
                                <i class="fas fa-info-circle text-blue-600 text-xl mr-3"></i>
                                <h4 class="text-lg font-semibold text-gray-900">借阅须知</h4>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                                <div class="flex items-center p-3 bg-white/50 rounded-xl">
                                    <i class="fas fa-clock text-blue-500 mr-3"></i>
                                    <div>
                                        <div class="font-medium text-gray-900">借阅期限</div>
                                        <div class="text-gray-600">30天</div>
                                    </div>
                                </div>
                                <div class="flex items-center p-3 bg-white/50 rounded-xl">
                                    <i class="fas fa-redo text-green-500 mr-3"></i>
                                    <div>
                                        <div class="font-medium text-gray-900">续借次数</div>
                                        <div class="text-gray-600">最多1次</div>
                                    </div>
                                </div>
                                <div class="flex items-center p-3 bg-white/50 rounded-xl">
                                    <i class="fas fa-books text-purple-500 mr-3"></i>
                                    <div>
                                        <div class="font-medium text-gray-900">借阅限制</div>
                                        <div class="text-gray-600">最多5本</div>
                                    </div>
                                </div>
                                <div class="flex items-center p-3 bg-white/50 rounded-xl">
                                    <i class="fas fa-user text-orange-500 mr-3"></i>
                                    <div>
                                        <div class="font-medium text-gray-900">当前状态</div>
                                        <div class="text-gray-600"><?php echo $user_current_borrows; ?>/5 本</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 图书信息 -->
                        <div class="bg-gray-50 rounded-2xl p-6 mb-6">
                            <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-bookmark text-blue-600 mr-3"></i>
                                图书详情
                            </h4>
                            <div class="space-y-3">
                                <div class="flex">
                                    <span class="font-medium text-gray-700 w-20">书名:</span>
                                    <span id="borrowBookTitle" class="text-gray-900 font-medium flex-1"></span>
                                </div>
                                <div class="flex">
                                    <span class="font-medium text-gray-700 w-20">作者:</span>
                                    <span id="borrowBookAuthor" class="text-gray-600 flex-1"></span>
                                </div>
                                <div class="flex">
                                    <span class="font-medium text-gray-700 w-20">期限:</span>
                                    <span class="text-gray-600 flex-1">借阅30天，到期日为 <span class="font-medium text-blue-600"><?php echo date('Y年m月d日', strtotime('+30 days')); ?></span></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 重要提醒 -->
                        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 mb-6">
                            <div class="flex items-start">
                                <i class="fas fa-exclamation-triangle text-amber-500 mr-3 mt-1"></i>
                                <div class="text-sm text-amber-800">
                                    <div class="font-medium mb-2">重要提醒</div>
                                    <ul class="space-y-1 text-xs">
                                        <li>• 请按时归还，逾期将影响后续借阅权限</li>
                                        <li>• 如需续借，请在到期前3天内办理</li>
                                        <li>• 图书损坏或丢失需要照价赔偿</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 按钮区域 -->
                    <div class="bg-gray-50 px-8 py-6 flex flex-col sm:flex-row gap-3 sm:justify-end rounded-b-3xl">
                        <button 
                            type="button"
                            onclick="closeBorrowModal()"
                            class="px-6 py-3 text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 focus:outline-none focus:ring-4 focus:ring-gray-200 transition-all duration-200 font-medium"
                        >
                            <i class="fas fa-times mr-2"></i>
                            取消
                        </button>
                        <button 
                            type="submit"
                            class="px-8 py-3 text-white bg-gradient-to-r from-blue-600 to-purple-600 rounded-xl hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-4 focus:ring-blue-500/20 transition-all duration-300 font-medium shadow-lg hover:shadow-xl"
                        >
                            <i class="fas fa-check mr-2"></i>
                            确认借阅
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // 借阅确认功能
        function confirmBorrow(bookId, bookTitle, bookAuthor) {
            console.log('confirmBorrow called:', bookId, bookTitle, bookAuthor);
            document.getElementById('borrowBookId').value = bookId;
            document.getElementById('borrowBookTitle').textContent = bookTitle;
            document.getElementById('borrowBookAuthor').textContent = bookAuthor;
            document.getElementById('borrowModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden'; // 防止背景滚动
        }

        function closeBorrowModal() {
            document.getElementById('borrowModal').classList.add('hidden');
            document.body.style.overflow = 'auto'; // 恢复滚动
        }

        // DOM加载完成后绑定事件
        document.addEventListener('DOMContentLoaded', function() {
            // 模态框背景点击关闭
            document.getElementById('borrowModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeBorrowModal();
                }
            });

            // ESC键关闭模态框
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeBorrowModal();
                }
            });

            // 表单提交验证
            document.getElementById('borrowForm').addEventListener('submit', function(e) {
                const currentBorrows = <?php echo $user_current_borrows; ?>;
                if (currentBorrows >= 5) {
                    e.preventDefault();
                    alert('您的借阅数量已达上限（5本），请先归还部分图书后再借阅。');
                    closeBorrowModal();
                    return false;
                }

                // 显示loading状态
                const submitButton = this.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>借阅中...';
                }
            });

            // 自动清除提示消息
            setTimeout(function() {
                const alerts = document.querySelectorAll('[class*="bg-green"], [class*="bg-red"]');
                alerts.forEach(function(alert) {
                    if (alert.textContent.includes('借阅成功') || alert.textContent.includes('失败')) {
                        alert.style.transition = 'all 0.5s ease-out';
                        alert.style.transform = 'translateX(100%)';
                        alert.style.opacity = '0';
                        setTimeout(function() {
                            alert.remove();
                        }, 500);
                    }
                });
            }, 5000);

            // 添加图书卡片进入动画
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-slide-up');
                    }
                });
            }, {
                threshold: 0.1
            });

            document.querySelectorAll('.book-card').forEach((card) => {
                observer.observe(card);
            });

            // 搜索框聚焦效果
            const searchInput = document.querySelector('input[name="keyword"]');
            if (searchInput) {
                searchInput.addEventListener('focus', function() {
                    this.parentElement.classList.add('ring-4', 'ring-blue-500/20');
                });
                
                searchInput.addEventListener('blur', function() {
                    this.parentElement.classList.remove('ring-4', 'ring-blue-500/20');
                });
            }

            // 页面加载动画
            document.body.classList.add('animate-fade-in');
        });

        // 平滑滚动到顶部
        function scrollToTop() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        // 添加返回顶部按钮功能
        window.addEventListener('scroll', function() {
            const scrollButton = document.getElementById('scrollToTop');
            if (window.pageYOffset > 300) {
                if (!scrollButton) {
                    const button = document.createElement('button');
                    button.id = 'scrollToTop';
                    button.className = 'fixed bottom-8 right-8 bg-gradient-to-r from-blue-600 to-purple-600 text-white p-4 rounded-full shadow-lg hover:shadow-xl transition-all duration-300 z-40 hover:scale-110';
                    button.innerHTML = '<i class="fas fa-arrow-up"></i>';
                    button.onclick = scrollToTop;
                    document.body.appendChild(button);
                }
            } else {
                if (scrollButton) {
                    scrollButton.remove();
                }
            }
        });
    </script>

    <style>
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .book-card {
            opacity: 0;
            transform: translateY(20px);
        }
        
        .book-card.animate-slide-up {
            animation: slideUp 0.6s ease-out forwards;
        }
        
        /* 自定义滚动条 */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(243, 244, 246, 0.8);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(to bottom, #3b82f6, #8b5cf6);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(to bottom, #2563eb, #7c3aed);
        }
        
        /* 玻璃态效果 */
        .backdrop-blur-sm {
            backdrop-filter: blur(8px);
        }
        
        /* 悬停效果 */
        .book-card:hover {
            transform: translateY(-8px) scale(1.02);
        }
        
        /* 加载动画 */
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes bounceGentle {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        
        /* 响应式优化 */
        @media (max-width: 640px) {
            .book-card {
                margin: 0 auto;
                max-width: 300px;
            }
        }
    </style>
</body>
</html>