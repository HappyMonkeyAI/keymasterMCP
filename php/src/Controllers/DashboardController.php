<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

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
                ? '<span style="color: #22c55e;">● Configured</span>' 
                : '<span style="color: #ef4444;">○ Not configured</span>';
            $servicesHtml .= "<tr><td>{$service['name']}</td><td>{$status}</td></tr>";
        }

        $projectsHtml = '';
        foreach ($projects as $project) {
            $desc = htmlspecialchars($project['description'] ?? '-');
            $projectsHtml .= "<tr><td><a href=\"/projects/{$project['id']}\" style=\"color: #3b82f6;\">{$project['name']}</a></td><td>{$desc}</td><td>{$project['created_at']}</td></tr>";
        }

        $this->render("
        <div class=\"section\">
            <h2>Available Services <a href=\"/credentials\" class=\"btn\">Manage Credentials</a></h2>
            <table>
                <thead><tr><th>Service</th><th>Status</th></tr></thead>
                <tbody>{$servicesHtml}</tbody>
            </table>
        </div>
        <div class=\"section\">
            <h2>Projects <a href=\"/projects/new\" class=\"btn\">New Project</a></h2>
            <table>
                <thead><tr><th>Name</th><th>Description</th><th>Created</th></tr></thead>
                <tbody>{$projectsHtml}</tbody>
            </table>
        </div>");
    }

    private function render($content) {
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Keymaster MCP</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; }
        .header { background: #1e293b; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #334155; }
        .header h1 { font-size: 1.25rem; color: #f8fafc; }
        .header a { color: #94a3b8; text-decoration: none; margin-left: 1.5rem; }
        .header a:hover { color: #f8fafc; }
        .container { padding: 2rem; max-width: 1400px; margin: 0 auto; }
        .section { margin-bottom: 2rem; }
        .section h2 { font-size: 1.125rem; margin-bottom: 1rem; color: #f8fafc; display: flex; justify-content: space-between; align-items: center; }
        .btn { padding: 0.5rem 1rem; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; font-size: 0.875rem; }
        .btn:hover { background: #2563eb; }
        table { width: 100%; border-collapse: collapse; background: #1e293b; border-radius: 8px; overflow: hidden; }
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #334155; }
        th { background: #334155; color: #f8fafc; font-weight: 500; }
        tr:hover { background: #273548; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Keymaster MCP</h1>
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
