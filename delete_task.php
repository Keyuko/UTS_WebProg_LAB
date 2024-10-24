<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_id'])) {
    $task_id = $_POST['task_id'];
    $user_id = $_SESSION['user_id'];

    try {
        $dsn = "mysql:host=localhost;dbname=todo_list";
        $pdo = new PDO($dsn, "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sql = "DELETE FROM tasks WHERE id = ? AND user_id = ?";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$task_id, $user_id])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Failed to delete task']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Invalid request']);
}
?>
