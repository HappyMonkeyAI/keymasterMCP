<?php

namespace App\Controllers;

class DashboardController
{
    private $api;
    
    public function __construct($api) {
        $this->api = $api;
    }

    public function index()
    {
        $servicesResult = $this->api->getServices();
        $projectsResult = $this->api->getProjects();
        
        $services = $servicesResult['status'] === 200 ? $servicesResult['data'] : [];
        $projects = $projectsResult['status'] === 200 ? $projectsResult['data'] : [];

        $servicesHtml = '';
        foreach ($services as $service) {
            $status = $service['configured'] 
                ? '<span class="status-chip status-success">● Active</span>' 
                : '<span class="status-chip status-dim">○ Inactive</span>';
            $servicesHtml .= "<tr><td><span class=\"mono\">{$service['name']}</span></td><td>{$status}</td></tr>";
        }

        $projectsHtml = '';
        foreach ($projects as $project) {
            $desc = htmlspecialchars($project['description'] ?? 'No description');
            $projectsHtml .= "
            <tr>
                <td><a href=\"/projects/{$project['id']}\" class=\"project-link\">{$project['name']}</a></td>
                <td><span class=\"dim-text\">{$desc}</span></td>
                <td><span class=\"mono dim-text\">" . date('Y-m-d', strtotime($project['created_at'])) . "</span></td>
            </tr>";
        }

        $this->render("
        <div class=\"dashboard-grid\">
            <div class=\"section-card\">
                <div class=\"card-header\">
                    <h2 class=\"gradient-text\">Service Health</h2>
                    <a href=\"/credentials\" class=\"btn btn-ghost\">Manage Keys</a>
                </div>
                <table>
                    <thead><tr><th>Service</th><th>Status</th></tr></thead>
                    <tbody>{$servicesHtml}</tbody>
                </table>
            </div>
            <div class=\"section-card\">
                <div class=\"card-header\">
                    <h2 class=\"gradient-text\">Active Projects</h2>
                    <a href=\"/projects/new\" class=\"btn btn-primary\">+ New Project</a>
                </div>
                <table>
                    <thead><tr><th>Name</th><th>Description</th><th>Created</th></tr></thead>
                    <tbody>{$projectsHtml}</tbody>
                </table>
            </div>
        </div>");
    }

    private function render($content) {
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Keymaster</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&family=JetBrains+Mono&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #030712;
            --card-bg: rgba(17, 24, 39, 0.6);
            --border: rgba(255, 255, 255, 0.08);
            --primary: #3b82f6;
            --primary-glow: rgba(59, 130, 246, 0.4);
            --text-main: #f8fafc;
            --text-dim: #94a3b8;
            --success: #22c55e;
            --danger: #ef4444;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: "Inter", sans-serif; 
            background: var(--bg); 
            color: var(--text-main); 
            min-height: 100vh;
            background-image: 
                radial-gradient(circle at 0% 0%, rgba(59, 130, 246, 0.1) 0%, transparent 40%),
                radial-gradient(circle at 100% 100%, rgba(147, 51, 234, 0.1) 0%, transparent 40%);
            line-height: 1.5;
        }

        .header { 
            backdrop-filter: blur(12px);
            background: rgba(3, 7, 18, 0.8);
            padding: 1.25rem 2.5rem; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            border-bottom: 1px solid var(--border); 
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .header h1 { font-size: 1.25rem; font-weight: 600; letter-spacing: -0.025em; background: linear-gradient(to right, #fff, var(--text-dim)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .header nav a { color: var(--text-dim); text-decoration: none; margin-left: 2rem; font-size: 0.9rem; transition: color 0.2s; font-weight: 500; }
        .header nav a:hover { color: var(--text-main); }

        .container { padding: 3rem 2rem; max-width: 1400px; margin: 0 auto; }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
        }

        .section-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .gradient-text {
            font-size: 1.1rem;
            font-weight: 600;
            color: #fff;
        }

        .btn { 
            padding: 0.6rem 1.25rem; 
            border-radius: 0.75rem; 
            font-weight: 600; 
            cursor: pointer; 
            text-decoration: none; 
            font-size: 0.85rem; 
            display: inline-flex;
            align-items: center;
            transition: all 0.2s;
            border: none;
        }
        .btn-primary { 
            background: var(--primary); 
            color: white; 
            box-shadow: 0 0 20px var(--primary-glow);
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 0 30px var(--primary-glow); }
        .btn-ghost { background: rgba(255, 255, 255, 0.05); color: var(--text-main); border: 1px solid var(--border); }
        .btn-ghost:hover { background: rgba(255, 255, 255, 0.1); }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border); }
        th { color: var(--text-dim); font-weight: 500; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; }
        tr:hover { background: rgba(255, 255, 255, 0.02); }
        
        .mono { font-family: "JetBrains Mono", monospace; font-size: 0.85rem; }
        .dim-text { color: var(--text-dim); font-size: 0.9rem; }
        
        .project-link { color: var(--primary); text-decoration: none; font-weight: 600; transition: color 0.2s; }
        .project-link:hover { color: #60a5fa; }

        .status-chip {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .status-success { color: var(--success); background: rgba(34, 197, 94, 0.1); }
        .status-dim { color: var(--text-dim); background: rgba(148, 163, 184, 0.1); }

        @media (max-width: 1024px) {
            .dashboard-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Keymaster</h1>
        <nav>
            <a href="/">Dashboard</a>
            <a href="/credentials">Credentials</a>
            <a href="/projects">Projects</a>
            <a href="/logout">Logout</a>
        </nav>
    </div>
    <div class="container">' . $content . '</div>
</body>
</html>';
    }
}
