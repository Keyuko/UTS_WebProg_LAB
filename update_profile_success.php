<?php
session_start();
$dsn = "mysql:host=localhost;dbname=todo_list";
$pdo = new PDO($dsn, "root", "");

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_POST['username'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

$sql = "SELECT id FROM login_user WHERE (username = ? OR email = ?) AND id != ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$username, $email, $user_id]);
$existing_user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing_user) {
    $_SESSION['error_message'] = "Username or email is already taken.";
    header('Location: user_dashboard.php');
    exit;
}

$sql = "UPDATE login_user SET username = ?, email = ?" . ($password ? ", password = ?" : "") . " WHERE id = ?";
$params = [$username, $email];
if ($password) {
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    $params[] = $hashed_password;
}
$params[] = $user_id;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$_SESSION['success_message'] = "Profile updated successfully.";
header('Location: user_dashboard.php');
exit;
?>