<?php

session_start();

$host = "localhost";
$dbname = "recordtrackingsystem";
$username = "root";
$dbpassword = "";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $dbpassword
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("Database connection failed.");
}


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        die("Please enter your email and password.");
    }

    $stmt = $pdo->prepare(
        "SELECT id, name, email, password
         FROM users
         WHERE email = ?
         LIMIT 1"
    );

    $stmt->execute([$email]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user["password"])) {

        session_regenerate_id(true);

        $_SESSION["user_id"] = $user["id"];
        $_SESSION["user_name"] = $user["name"];
        $_SESSION["user_email"] = $user["email"];

        header("Location: dashboard.php");
        exit;

    } else {

        echo "Invalid email or password.";

    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Login & Registration</title>

  
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
  >
  <link rel="stylesheet" href="style.css">
</head>

<body>

  <div class="container">

    
    <div class="form-container">

      <div class="form-box login">

        <div class="title">Login</div>

        <form action="login.php" method="POST">
          <div class="input-box">
            <i class="fa-solid fa-envelope"></i>
            <input type="email" placeholder="Enter your email" required>
          </div>

          <div class="input-box">
            <i class="fa-solid fa-lock"></i>
            <input type="password" placeholder="Enter your password" required>
          </div>

          <div class="forgot">
            <a href="#">Forgot password?</a>
          </div>

          <button type="submit">Login</button>

          <p class="signup-text">
            Don't have an account?
            <a href="#" id="showSignup">Signup now</a>
          </p>
        </form>

      </div>


      <div class="form-box signup">

        <div class="title">Signup</div>

        <form>
          <div class="input-box">
            <i class="fa-solid fa-user"></i>
            <input type="text" placeholder="Enter your name" required>
          </div>

          <div class="input-box">
            <i class="fa-solid fa-envelope"></i>
            <input type="email" placeholder="Enter your email" required>
          </div>

          <div class="input-box">
            <i class="fa-solid fa-lock"></i>
            <input type="password" placeholder="Create a password" required>
          </div>

          <button type="submit">Signup</button>

          <p class="signup-text">
            Already have an account?
            <a href="#" id="showLogin">Login now</a>
          </p>
        </form>

      </div>

    </div>


    
    <div class="image-container">

      <div class="image-content">

      </div>

    </div>

  </div>


  <script>
    const loginForm = document.querySelector(".login");
    const signupForm = document.querySelector(".signup");

    document.getElementById("showSignup").addEventListener("click", function(e) {
      e.preventDefault();

      loginForm.style.display = "none";
      signupForm.style.display = "block";
    });

    document.getElementById("showLogin").addEventListener("click", function(e) {
      e.preventDefault();

      signupForm.style.display = "none";
      loginForm.style.display = "block";
    });
  </script>

</body>
</html>