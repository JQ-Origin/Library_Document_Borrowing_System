<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>图书馆管理系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">图书馆管理系统</a>
            <div class="navbar-nav">
                <?php if ($role === 'user'): ?>
                    <a class="nav-link" href="user_center.php">个人中心</a>
                <?php elseif ($role === 'admin'): ?>
                    <a class="nav-link" href="book_manage.php">图书管理</a>
                    <a class="nav-link" href="borrow_manage.php">借阅管理</a>
                <?php endif; ?>
                <a class="nav-link" href="logout.php">退出登录</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        欢迎，<?php echo $_SESSION['username']; ?>
                    </div>
                    <div class="card-body">
                        <p>角色：<?php echo $role === 'user' ? '普通用户' : '管理员'; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-9">
                <!-- 图书搜索模块 -->
                <div class="card mb-4" id="search">
                    <div class="card-header bg-success text-white">
                        图书查询
                    </div>
                    <div class="card-body">
                        <form method="GET" action="search.php">
                            <div class="input-group">
                                <input type="text" name="keyword" class="form-control" placeholder="输入书名/作者/ISBN">
                                <button type="submit" class="btn btn-success">搜索</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 我的借阅模块 -->
                <div class="card" id="borrow">
                    <div class="card-header bg-warning text-dark">
                        我的借阅
                    </div>
                    <div class="card-body">
                        <?php include 'user_borrow.php'; ?>
                    </div>
                </div>
                    <div class="card-header">
                        <?php echo $role === 'user' ? '图书列表' : '系统概览'; ?>
                    </div>
                    <div class="card-body">
                        <!-- 内容区域待后续功能开发 -->
                        <p class="text-muted">功能开发中，敬请期待...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>