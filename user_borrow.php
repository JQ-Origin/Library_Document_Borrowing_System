<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $conn->prepare("SELECT b.title, br.borrow_date, br.return_date, br.status 
        FROM borrow_records br
        JOIN books b ON br.book_id = b.id
        WHERE br.user_id = ?
        ORDER BY br.borrow_date DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo '<table class="table table-hover">';
        echo '<thead><tr><th>书名</th><th>借阅日期</th><th>应还日期</th><th>状态</th></tr></thead>';
        echo '<tbody>';
        while ($row = $result->fetch_assoc()) {
            echo '<tr>';
            echo '<td>'.htmlspecialchars($row['title']).'</td>';
            echo '<td>'.$row['borrow_date'].'</td>';
            echo '<td>'.($row['return_date'] ?? '--').'</td>';
            echo '<td>';
            switch ($row['status']) {
                case '借出中':
                    echo '<span class="badge bg-warning">借出中</span>';
                    break;
                case '已归还':
                    echo '<span class="badge bg-success">已归还</span>';
                    break;
                default:
                    echo htmlspecialchars($row['status']);
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<div class="alert alert-info">暂无借阅记录</div>';
    }
} catch (Exception $e) {
    echo '<div class="alert alert-danger">数据加载失败：'.htmlspecialchars($e->getMessage()).'</div>';
}
?>