<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: ../login/login.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Profile</title>
  <style>body{font-family:Arial,Helvetica,sans-serif;padding:40px}</style>
</head>
<body>
  <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
  <p>This is a simple protected page. You are logged in.</p>
  <p><a href="logout.php">Log out</a></p>
</body>
</html>
