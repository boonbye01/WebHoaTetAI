<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

start_session();
$pdo = db();

$admin_count = (int)$pdo->query('SELECT COUNT(*) AS c FROM admin_user')->fetchColumn();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Vui lòng nhập tên đăng nhập và mật khẩu.';
    } else if ($action === 'create' && $admin_count === 0) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('INSERT INTO admin_user (username, password_hash, created_at) VALUES (?, ?, NOW())');
        $stmt->execute([$username, $hash]);
        $_SESSION['admin_user_id'] = (int)$pdo->lastInsertId();
        $_SESSION['admin_username'] = $username;
        header('Location: index.php');
        exit;
    } else if ($action === 'login') {
        $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admin_user WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['admin_user_id'] = (int)$user['id'];
            $_SESSION['admin_username'] = $user['username'];
            header('Location: index.php');
            exit;
        }
        $error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Đăng nhập quản trị</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <div class="container">
    <header class="header">
      <h1><?php echo $admin_count === 0 ? 'Tạo tài khoản quản trị' : 'Đăng nhập quản trị'; ?></h1>
    </header>

    <?php if ($error !== ''): ?>
      <div class="notice">Lỗi: <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post" class="login-form">
      <input type="hidden" name="action" value="<?php echo $admin_count === 0 ? 'create' : 'login'; ?>">
      <div class="field">
        <label>Tên đăng nhập</label>
        <input type="text" name="username" required>
      </div>
      <div class="field">
        <label>Mật khẩu</label>
        <input type="password" name="password" required>
      </div>
      <div class="actions">
        <button type="submit" class="button"><?php echo $admin_count === 0 ? 'Tạo tài khoản' : 'Đăng nhập'; ?></button>
      </div>
    </form>
  </div>
</body>
</html>
