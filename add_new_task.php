<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $status = trim($_POST['status']);
    $due_date = $_POST['due_date'];
    $participants = trim($_POST['participants']);
    $user_id = $_SESSION['user_id'];

    if (empty($title) || empty($status) || empty($due_date)) {
        echo json_encode(['error' => 'Title, status, and due date are required.']);
        exit;
    }

    try {
        $dsn = "mysql:host=localhost;dbname=todo_list;charset=utf8mb4";
        $pdo = new PDO($dsn, "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
        exit;
    }

    $image_path = null;
    if (isset($_FILES['task_image']) && $_FILES['task_image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($_FILES['task_image']['type'], $allowed_types)) {
            echo json_encode(['error' => 'Invalid file type.']);
            exit;
        }
        if ($_FILES['task_image']['size'] > 2 * 1024 * 1024) {
            echo json_encode(['error' => 'File size exceeds 2MB.']);
            exit;
        }

        $image_name = time() . '_' . basename($_FILES['task_image']['name']);
        $image_path = 'uploads/' . $image_name;
        if (!move_uploaded_file($_FILES['task_image']['tmp_name'], $image_path)) {
            echo json_encode(['error' => 'Failed to upload image.']);
            exit;
        }
    }

    try {
        $sql = "INSERT INTO tasks (title, description, status, due_date, participants, image_path, user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $description, $status, $due_date, $participants, $image_path, $user_id]);

        $task_id = $pdo->lastInsertId();

        echo json_encode([
            'id' => $task_id,
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'due_date' => $due_date,
            'participants' => $participants,
            'image_path' => $image_path
        ]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Failed to add task: ' . $e->getMessage()]);
        exit;
    }
} else {
    echo json_encode(['error' => 'Invalid request']);
}
