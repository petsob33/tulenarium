<?php
require_once 'config.php';

// Pokud je již přihlášen, přesměruj na hlavní stránku
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$loginError = false;

// Zpracování přihlášení
if (isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (login($username, $password)) {
        // Přesměrování na původní stránku nebo na hlavní stránku
        $redirect = $_GET['redirect'] ?? 'index.php';
        header('Location: ' . $redirect);
        exit;
    } else {
        $loginError = true;
    }
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Přihlášení - Tulenarium</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1.6;
        }
        
        .login-container {
            background: #2d2d2d;
            border: 1px solid #404040;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        
        .login-container h1 {
            margin-bottom: 10px;
            color: #ffffff;
            font-size: 2rem;
            font-weight: 300;
        }
        
        .login-container p {
            margin-bottom: 30px;
            color: #cccccc;
            font-size: 1rem;
        }
        
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #ffffff;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #404040;
            border-radius: 10px;
            background: #1a1a1a;
            color: #ffffff;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .form-group input:hover {
            border-color: #555555;
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #ffffff;
            background: #333333;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
            transform: translateY(-1px);
        }
        
        .btn {
            background: #ffffff;
            color: #1a1a1a;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 10px;
        }
        
        .btn:hover {
            background: #f0f0f0;
            transform: translateY(-2px);
        }
        
        .error {
            background: #2d1b1f;
            color: #ff6b6b;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #ff6b6b;
        }
        
        .logo {
            font-size: 3rem;
            margin-bottom: 20px;
            color: #ffffff;
        }
        
        @media (max-width: 768px) {
            .login-container {
                margin: 20px;
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">🏛️</div>
        <h1>Tulenarium</h1>
        <p>Přihlaste se pro pokračování</p>
        
        <?php if ($loginError): ?>
            <div class="error">
                Neplatné uživatelské jméno nebo heslo!
            </div>
        <?php endif; ?>
        
        <form method="post">
            <div class="form-group">
                <label for="username">Uživatelské jméno:</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Heslo:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" name="login" class="btn">Přihlásit se</button>
        </form>
    </div>
</body>
</html>

