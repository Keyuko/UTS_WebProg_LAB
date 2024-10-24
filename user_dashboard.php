<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$dsn = "mysql:host=localhost;dbname=todo_list";
$pdo = new PDO($dsn, "root", "");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_task_id'])) {
    $task_id = $_POST['delete_task_id'];
    $user_id = $_SESSION['user_id'];

    $sql = "DELETE FROM tasks WHERE id = ? AND user_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$task_id, $user_id]);

    header('Location: user_dashboard.php');
    exit;
}

$sql = "SELECT * FROM tasks WHERE user_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['user_id']]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$grouped_tasks = [
    'To do' => [],
    'In progress' => [],
    'On check' => [],
    'Done' => []
];

foreach ($tasks as $task) {
    $status = ucfirst(strtolower($task['status'])) ?? 'To do';
    if (isset($grouped_tasks[$status])) {
        $grouped_tasks[$status][] = $task;
    } else {
        $grouped_tasks['To do'][] = $task;
    }
}

$user_id = $_SESSION['user_id'];
$sql = "SELECT username, email FROM login_user WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: index.php');
    exit;
}

$total_tasks = count($tasks);
$tasks_done = count($grouped_tasks['Done']);
$tasks_remaining = $total_tasks - $tasks_done;

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modern Kanban Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.0.0/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1a202c, #2d3748);
            color: white;
            font-family: 'Poppins', sans-serif;
        }

        .kanban-board {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            overflow-x: auto;
            padding: 20px;
            margin-top: 30px;
        }

        .kanban-column {
            width: 100%;
            max-width: 350px;
            flex: 1 1 100%;
            background-color: #2d3748;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
            margin-bottom: 20px;
        }

        .kanban-column h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #e2e8f0;
            margin-bottom: 20px;
            border-bottom: 2px solid #4a5568;
        }

        .task-card {
            background-color: #4a5568;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            cursor: grab;
        }

        .task-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
        }

        .task-card img {
            max-width: 100%;
            border-radius: 8px;
            margin-top: 12px;
        }

        .form-container {
            background-color: #2d3748;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
            margin-bottom: 40px;
        }

        .form-container h2 {
            color: #f7fafc;
            font-size: 1.5rem;
            margin-bottom: 12px;
            font-weight: 700;
        }

        .input-field input,
        .input-field textarea,
        .input-field select {
            background-color: #4a5568;
            color: #f7fafc;
            border: none;
            border-radius: 8px;
            padding: 10px;
            width: 100%;
            margin-top: 8px;
        }

        .input-field input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .kanban-column.over {
            border: 2px dashed #ecc94b;
        }

        .empty-message {
            color: #9ca3af;
        }

        .button-container {
            margin-top: 10px;
            display: flex;
            gap: 10px;
        }

        .edit-button,
        .delete-button {
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .edit-button {
            background-color: #38b2ac;
            color: white;
        }

        .edit-button:hover {
            background-color: #319795;
        }

        .delete-button {
            background-color: #e53e3e;
            color: white;
        }

        .delete-button:hover {
            background-color: #c53030;
        }

        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            visibility: hidden;
            opacity: 0;
            transition: visibility 0s, opacity 0.3s;
        }

        .modal.active {
            visibility: visible;
            opacity: 1;
        }

        .modal-content {
            background-color: #2d3748;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
            width: 400px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .close-button {
            background: transparent;
            border: none;
            color: #e53e3e;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .toast {
            background-color: #38b2ac;
            color: #ffffff;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            opacity: 1;
            animation: fadeout 5s forwards;
        }

        @keyframes fadeout {
            0% {
                opacity: 1;
            }

            80% {
                opacity: 1;
            }

            100% {
                opacity: 0;
            }
        }

        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            width: 250px;
            background-color: #1a202c;
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.5);
        }

        .navbar-logo {
            display: flex;
            align-items: center;
            margin-bottom: 40px;
        }

        .navbar-logo img {
            width: 40px;
            height: 40px;
            margin-right: 10px;
        }

        .navbar-links {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .navbar-links a {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #f7fafc;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }

        .navbar-links a:hover {
            color: #ecc94b;
        }

        .navbar-links img {
            width: 24px;
            height: 24px;
        }

        .user-profile {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            margin-bottom: 40px;
        }

        .user-profile-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-bottom: 10px;
        }

        .user-name {
            font-weight: bold;
            color: #f7fafc;
        }

        .user-status {
            color: #38b2ac;
            font-size: 0.9rem;
        }

        .progress-overview {
            margin-top: 30px;
            padding: 20px;
            background-color: #2d3748;
            border-radius: 10px;
            text-align: center;
        }

        .progress-title {
            color: #ecc94b;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .progress-item {
            color: #f7fafc;
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="navbar-logo">
            <img src="https://img.icons8.com/color/48/null/task.png" alt="Kanban Logo">
            <h1 class="text-2xl font-bold text-yellow-400">Kanban Dashboard</h1>
        </div>
        <div class="user-profile">
            <img src="https://img.icons8.com/ios-filled/50/38b2ac/user.png" alt="User Icon" class="user-profile-icon">
            <p class="user-name">Hello, <?= htmlspecialchars($user['username']) ?></p>
            <p class="user-status">Status: Online</p>
        </div>
        <div class="navbar-links">
            <a href="#" onclick="openAddTaskModal()">
                <img src="https://img.icons8.com/ios-filled/50/38b2ac/add.png" alt="Add Icon">
                Add New Task
            </a>
            <a href="#" onclick="openEditProfileModal()">
                <img src="https://img.icons8.com/ios-filled/50/38b2ac/edit-user-male.png" alt="Edit Profile Icon">
                Edit Profile
            </a>
            <a href="logout.php">
                <img src="https://img.icons8.com/ios-filled/50/38b2ac/exit.png" alt="Logout Icon">
                Logout
            </a>
            <div class="progress-overview">
                <h3 class="progress-title">Progress Overview</h3>
                <p class="progress-item total-tasks">Total Tasks: <?= $total_tasks ?></p>
                <p class="progress-item tasks-done">Tasks Completed: <?= $tasks_done ?></p>
                <p class="progress-item tasks-remaining">Tasks Remaining: <?= $tasks_remaining ?></p>
            </div>

        </div>
    </nav>

    <div class="container mx-auto mt-12" style="margin-left: 270px;">

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-500 text-white p-4 rounded mb-4">
                <?= htmlspecialchars($_SESSION['success_message']) ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-500 text-white p-4 rounded mb-4">
                <?= htmlspecialchars($_SESSION['error_message']) ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

 