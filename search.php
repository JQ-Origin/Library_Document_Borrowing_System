<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 安全过滤搜索关键词
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// 准备SQL查询
$search_sql = "SELECT * FROM books 
    WHERE title LIKE ? 
    OR author LIKE ? 
    OR isbn LIKE ?
    ORDER BY title ASC
    LIMIT ?, ?";

$count_sql = "SELECT COUNT(*) as total FROM books 
    WHERE title LIKE ? 
    OR author LIKE ? 
    OR isbn LIKE ?";

// 绑定参数
$search_term = "%$keyword%";

// 获取总记录数
$stmt = $conn->prepare($count_sql);
$stmt->bind_param("sss", $search_term, $search_term, $search_term);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// 获取分页数据
$stmt = $conn->prepare($search_sql);
$stmt->bind_param("sssii", 
    $search_term, 
    $search_term, 
    $search_term,
    $offset,
    $per_page
);
$stmt->execute();
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total_pages = ceil($total / $per_page);
?>

<!DOCTYPE html>
<html>
<head>
    <title>图书搜索</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">图书馆管理系统</a>
            <div class="navbar-nav">
                <?php if ($_SESSION['role'] === 'user'): ?>
                    <a class="nav-link" href="user_center.php">个人中心</a>
                <?php elseif ($_SESSION['role'] === 'admin'): ?>
                    <a class="nav-link" href="book_manage.php">图书管理</a>
                    <a class="nav-link" href="borrow_manage.php">借阅管理</a>
                <?php endif; ?>
                <a class="nav-link" href="logout.php">退出登录</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                搜索结果（共<?php echo $total; ?>条）
            </div>
            <div class="card-body">
                <form method="GET" action="search.php">
                    <div class="input-group mb-3">
                        <input type="text" name="keyword" class="form-control" 
                            value="<?php echo htmlspecialchars($keyword); ?>"
                            placeholder="输入书名/作者/ISBN">
                        <button type="submit" class="btn btn-success">重新搜索</button>
                    </div>
                </form>

                <?php if (!empty($results)): ?>
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>书名</th>
                                <th>作者</th>
                                <th>ISBN</th>
                                <th>库存</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $book): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($book['title']); ?></td>
                                    <td><?php echo htmlspecialchars($book['author']); ?></td>
                                    <td><?php echo htmlspecialchars($book['isbn']); ?></td>
                                    <td><?php echo $book['available']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- 分页导航 -->
                    <nav>
                        <ul class="pagination">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" 
                                        href="search.php?keyword=<?php echo urlencode($keyword); ?>&page=<?php echo $i; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php else: ?>
                    <div class="alert alert-info">没有找到相关图书</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>