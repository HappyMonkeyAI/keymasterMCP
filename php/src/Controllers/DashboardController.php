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
        $orgResult = $this->api->getOrganization();
        $projectsResult = $this->api->getProjects();
        $logsResult = $this->api->get('/api/audit-logs?limit=5');
        
        $org = $orgResult['status'] === 200 ? $orgResult['data'] : ['name' => 'Keymaster', 'slug' => 'keymaster'];
        $projects = $projectsResult['status'] === 200 ? $projectsResult['data'] : [];
        $logs = $logsResult['status'] === 200 ? $logsResult['data'] : [];

        $projectsHtml = '';
        foreach ($projects as $project) {
            $type = ucfirst($project['type'] ?? 'secrets');
            $icon = $this->getProjectIcon($project['type'] ?? 'secrets');
            $projectsHtml .= "
            <a href=\"/projects/{$project['id']}\" class=\"project-card\">
                <div class=\"project-card-icon\">{$icon}</div>
                <div class=\"project-card-content\">
                    <h3>{$project['name']}</h3>
                    <span class=\"project-type\">{$type}</span>
                    <p>{$project['description']}</p>
                </div>
            </a>";
        }

        $logsHtml = '';
        foreach ($logs as $log) {
            $statusClass = $log['status'] === 'success' ? 'text-success' : 'text-danger';
            $time = date('H:i:s', strtotime($log['timestamp']));
            $logsHtml .= "
            <div class=\"log-item\">
                <span class=\"log-time\">{$time}</span>
                <span class=\"log-action\">{$log['action']}</span>
                <span class=\"log-status {$statusClass}\">{$log['status']}</span>
            </div>";
        }

        $this->render("
        <div class=\"app-layout\">
            <aside class=\"sidebar\">
                <div class=\"sidebar-header\">
                    <div class=\"org-badge\">
                        <span class=\"org-icon\">K</span>
                        <div class=\"org-info\">
                            <span class=\"org-name\">{$org['name']}</span>
                            <span class=\"org-plan\">Free Plan</span>
                        </div>
                    </div>
                </div>
                <nav class=\"sidebar-nav\">
                    <p class=\"nav-label\">Overview</p>
                    <a href=\"/\" class=\"active\">Projects</a>
                    <a href=\"/access-control\">Access Control</a>
                    <a href=\"/audit-logs\">Audit Logs</a>
                    <a href=\"/settings\">Organization Settings</a>
                    
                    <p class=\"nav-label\">Resources</p>
                    <a href=\"/credentials\">Credentials Vault</a>
                </nav>
                <div class=\"sidebar-footer\">
                    <a href=\"/logout\" class=\"logout-btn\">Logout</a>
                </div>
            </aside>

            <main class=\"main-content\">
                <header class=\"content-header\">
                    <div class=\"breadcrumb\">
                        <span>Projects</span>
                    </div>
                    <div class=\"header-actions\">
                        <a href=\"/projects/new\" class=\"btn btn-primary\">+ Add New Project</a>
                    </div>
                </header>

                <div class=\"page-body\">
                    <div class=\"welcome-section\">
                        <h1>My Projects</h1>
                        <p>Your team's complete security toolkit - organized and ready when you need them.</p>
                    </div>

                    <div class=\"project-grid\">
                        {$projectsHtml}
                    </div>

                    <div class=\"recent-activity section-card\" style=\"margin-top: 3rem;\">
                        <h2 class=\"gradient-text\">Recent Activity</h2>
                        <div class=\"activity-list\">
                            {$logsHtml}
                        </div>
                    </div>
                </div>
            </main>
        </div>", $org['name']);
    }

    private function getProjectIcon($type) {
        return match($type) {
            'secrets' => '🔒',
            'pki' => '📜',
            'kms' => '🔑',
            'ssh' => '💻',
            default => '📁'
        };
    }

    private function render($content, $orgName = 'Keymaster') {
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ' . $orgName . '</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #030712;
            --sidebar-bg: #090e1a;
            --card-bg: rgba(17, 24, 39, 0.4);
            --border: rgba(255, 255, 255, 0.06);
            --primary: #3b82f6;
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
            height: 100vh;
            overflow: hidden;
        }

        .app-layout { display: flex; height: 100vh; }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            padding: 1.5rem;
        }
        .org-badge {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 0.75rem;
            margin-bottom: 2rem;
        }
        .org-icon {
            width: 32px;
            height: 32px;
            background: var(--primary);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }
        .org-info { display: flex; flex-direction: column; }
        .org-name { font-weight: 600; font-size: 0.9rem; }
        .org-plan { font-size: 0.7rem; color: #fbbf24; font-weight: 600; text-transform: uppercase; }

        .nav-label { font-size: 0.7rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.05em; margin: 1.5rem 0 0.75rem 0.75rem; font-weight: 600; }
        .sidebar-nav a {
            display: block;
            padding: 0.6rem 0.75rem;
            color: var(--text-dim);
            text-decoration: none;
            font-size: 0.9rem;
            border-radius: 0.5rem;
            margin-bottom: 0.25rem;
            transition: all 0.2s;
        }
        .sidebar-nav a:hover { background: rgba(255, 255, 255, 0.05); color: var(--text-main); }
        .sidebar-nav a.active { background: rgba(59, 130, 246, 0.1); color: var(--primary); font-weight: 600; }

        .sidebar-footer { margin-top: auto; padding-top: 1.5rem; border-top: 1px solid var(--border); }
        .logout-btn { color: var(--text-dim); text-decoration: none; font-size: 0.9rem; }

        /* Main Content */
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; background: var(--bg); }
        .content-header {
            padding: 1rem 2.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(3, 7, 18, 0.5);
            backdrop-filter: blur(10px);
            position: sticky; top: 0; z-index: 10;
        }
        .breadcrumb { font-size: 0.9rem; color: var(--text-dim); }

        .page-body { padding: 3rem 2.5rem; max-width: 1200px; }
        .welcome-section { margin-bottom: 3rem; }
        .welcome-section h1 { font-size: 2rem; margin-bottom: 0.5rem; }
        .welcome-section p { color: var(--text-dim); }

        /* Project Grid */
        .project-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        .project-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 1.5rem;
            text-decoration: none;
            color: inherit;
            display: flex;
            gap: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .project-card:hover { border-color: var(--primary); background: rgba(59, 130, 246, 0.05); transform: translateY(-2px); }
        .project-card-icon { font-size: 1.5rem; padding-top: 0.25rem; }
        .project-card-content h3 { font-size: 1.1rem; margin-bottom: 0.25rem; }
        .project-type { font-size: 0.75rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem; display: block; }
        .project-card-content p { font-size: 0.9rem; color: var(--text-dim); line-height: 1.5; }

        /* Utilities */
        .btn { padding: 0.6rem 1.25rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 0.85rem; border: none; }
        .btn-primary { background: var(--primary); color: white; }
        .text-success { color: var(--success); }
        .text-danger { color: var(--danger); }

        .log-item { display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid var(--border); font-size: 0.85rem; }
        .log-time { color: var(--text-dim); width: 80px; }
        .log-action { flex: 1; font-weight: 500; }
        .section-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 1rem; padding: 1.5rem; }
    </style>
</head>
<body>
    ' . $content . '
</body>
</html>';
    }
}
