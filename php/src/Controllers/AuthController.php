<?php

namespace App\Controllers;

class AuthController
{
    private $settings;
    
    public function __construct($settings) {
        $this->settings = $settings;
    }

    public function login()
    {
        echo <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Keymaster MCP</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #0f172a; 
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center;
        }
        .login-container { 
            background: #1e293b; 
            padding: 2rem; 
            border-radius: 12px; 
            width: 100%; 
            max-width: 400px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        h1 { color: #f8fafc; margin-bottom: 1.5rem; text-align: center; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; color: #94a3b8; margin-bottom: 0.5rem; }
        input { 
            width: 100%; 
            padding: 0.75rem; 
            border: 1px solid #334155; 
            border-radius: 6px; 
            background: #0f172a;
            color: #f8fafc;
            font-size: 1rem;
        }
        input:focus { outline: none; border-color: #3b82f6; }
        button { 
            width: 100%; 
            padding: 0.75rem; 
            background: #3b82f6; 
            color: white; 
            border: none; 
            border-radius: 6px; 
            font-size: 1rem; 
            cursor: pointer;
            margin-top: 1rem;
        }
        button:hover { background: #2563eb; }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Keymaster MCP</h1>
        <form method="post">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit">Sign In</button>
        </form>
    </div>
</body>
</html>
HTML;
    }

    public function doLogin()
    {
        $data = $_POST;
        
        if ($data['username'] === $this->settings['admin_username'] && 
            $data['password'] === $this->settings['admin_password']) {
            $_SESSION['authenticated'] = true;
            header('Location: /');
            exit;
        }
        
        echo <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Keymaster MCP</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #0f172a; 
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center;
        }
        .login-container { 
            background: #1e293b; 
            padding: 2rem; 
            border-radius: 12px; 
            width: 100%; 
            max-width: 400px;
        }
        h1 { color: #f8fafc; margin-bottom: 1.5rem; text-align: center; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; color: #94a3b8; margin-bottom: 0.5rem; }
        input { 
            width: 100%; 
            padding: 0.75rem; 
            border: 1px solid #334155; 
            border-radius: 6px; 
            background: #0f172a;
            color: #f8fafc;
        }
        button { 
            width: 100%; 
            padding: 0.75rem; 
            background: #3b82f6; 
            color: white; 
            border: none; 
            border-radius: 6px; 
            cursor: pointer;
        }
        .error { color: #ef4444; margin-top: 1rem; text-align: center; }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Keymaster MCP</h1>
        <form method="post">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit">Sign In</button>
            <p class="error">Invalid credentials</p>
        </form>
    </div>
</body>
</html>
HTML;
    }
}
