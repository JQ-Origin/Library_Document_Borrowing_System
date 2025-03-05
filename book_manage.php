<?php
session_start();
require_once 'db.php';

// 权限验证
if ($_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// 处理新增/编辑图书
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $book_id = $_POST['book_id'] ?? null;
    $title = $_POST['title'];
    $author = $_POST['author'];
    $isbn = $_POST['isbn'];
    $category = $_POST['category'];
    $total = $_POST['total'];

    if ($book_id) {
        // 更新图书
        $stmt = $conn->prepare("UPDATE books SET title=?, author=?, isbn=?, category=?, total=? WHERE id=?");
        $stmt->bind_param("ssssii", $title, $author, $isbn, $category, $total, $book_id);
    } else {
        // 新增图书
        $stmt = $conn->prepare("INSERT INTO books (title, author, isbn, category, total, available) VALUES (?, ?, ?, ?, ?, ?)");
        $available = $total;
        $stmt->bind_param("ssssii", $title, $author, $isbn, $category, $total, $available);
    }

    if ($stmt->execute()) {
        header("Location: book_manage.php");
        exit();
    }
    $stmt->close();
}

// 处理删除请求
if (isset($_GET['delete'])) {
    $stmt = $conn->prepare("DELETE FROM books WHERE id=?");
    $stmt->bind_param("i", $_GET['delete']);
    $stmt->execute();
    $stmt->close();
    header("Location: book_manage.php");
    exit();
}

// 获取所有图书
$search_author = $_GET['author'] ?? '';
$search_category = $_GET['category'] ?? '';
$search_isbn = $_GET['isbn'] ?? '';

$where = [];
$params = [];
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
?>

<!DOCTYPE html>
<html>
<head>
    <title>图书管理</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">图书馆管理系统</a>
            <div class="navbar-nav">
                <a class="nav-link" href="book_manage.php">图书管理</a>
                <a class="nav-link" href="borrow_manage.php">借阅管理</a>
                <a class="nav-link" href="logout.php">退出登录</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h3 class="mb-4">图书管理</h3>
        
        <!-- 新增/编辑表单 -->
        <div class="card mb-4">
            <div class="card-header"><?= isset($_GET['edit']) ? '编辑图书' : '新增图书' ?></div>
            <div class="card-body">
                <form method="POST">
                    <?php if (isset($_GET['edit'])): 
                        $edit_book = $conn->query("SELECT * FROM books WHERE id=" . $_GET['edit'])->fetch_assoc();
                    ?>
                        <input type="hidden" name="book_id" value="<?= $edit_book['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">书名</label>
                            <input type="text" name="title" class="form-control" required 
                                value="<?= $edit_book['title'] ?? '' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">作者</label>
                            <input type="text" name="author" class="form-control" required
                                value="<?= $edit_book['author'] ?? '' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ISBN</label>
                            <input type="text" name="isbn" class="form-control" required
                                value="<?= $edit_book['isbn'] ?? '' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">分类</label>
                            <input type="text" name="category" class="form-control" required
                                value="<?= $edit_book['category'] ?? '' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">总数量</label>
                            <input type="number" name="total" class="form-control" min="1" required
                                value="<?= $edit_book['total'] ?? '' ?>">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">提交</button>
                            <?php if (isset($_GET['edit'])): ?>
                                <a href="book_manage.php" class="btn btn-secondary">取消</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- 图书列表 -->
        <div class="card">
            <div class="card-header">图书列表</div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>书名</th>
                            <th>作者</th>
                            <th>ISBN</th>
                            <th>分类</th>
                            <th>总数</th>
                            <th>可借</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($book = $books->fetch_assoc()): ?>
                        <tr>
                            <td><?= $book['id'] ?></td>
                            <td><?= htmlspecialchars($book['title']) ?></td>
                            <td><?= htmlspecialchars($book['author']) ?></td>
                            <td><?= $book['isbn'] ?></td>
                            <td><?= $book['category'] ?></td>
                            <td><?= $book['total'] ?></td>
                            <td><?= $book['available'] ?></td>
                            <td>
                                <a href="book_manage.php?edit=<?= $book['id'] ?>" class="btn btn-sm btn-warning">编辑</a>
                                <a href="book_manage.php?delete=<?= $book['id'] ?>" class="btn btn-sm btn-danger" 
                                    onclick="return confirm('确认删除该图书？')">删除</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 搜索表单 -->
    <div class="card mb-4">
        <div class="card-header">高级搜索</div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="author" class="form-control" placeholder="作者" value="<?= htmlspecialchars($search_author) ?>">
                </div>
                <div class="col-md-3">
                    <input type="text" name="category" class="form-control" placeholder="分类" value="<?= htmlspecialchars($search_category) ?>">
                </div>
                <div class="col-md-3">
                    <input type="text" name="isbn" class="form-control" placeholder="ISBN" value="<?= htmlspecialchars($search_isbn) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">搜索</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 分页控件 -->
    <nav>
        <ul class="pagination justify-content-center">
            <?php if ($page > 1): ?>
                <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page-1])) ?>">上一页</a></li>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page+1])) ?>">下一页</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</body>
</html>