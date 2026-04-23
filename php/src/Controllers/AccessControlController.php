<?php

namespace App\Controllers;

class AccessControlController
{
    private $api;
    
    public function __construct($api) {
        $this->api = $api;
    }

    public function index()
    {
        $orgResult = $this->api->getOrganization();
        $clientsResult = $this->api->getClients();
        
        $org = $orgResult['status'] === 200 ? $orgResult['data'] : ['name' => 'Keymaster', 'slug' => 'keymaster'];
        $clients = $clientsResult['status'] === 200 ? $clientsResult['data'] : [];

        $clientsHtml = '';
        foreach ($clients as $client) {
            $role = ucfirst($client['role'] ?? 'developer');
            $roleClass = $client['role'] === 'admin' ? 'role-admin' : 'role-dev';
            $lastUsed = $client['last_used_at'] ? date('Y-m-d H:i', strtotime($client['last_used_at'])) : 'Never';
            
            $clientsHtml .= "
            <tr>
                <td>
                    <div class=\"client-info\">
                        <span class=\"client-name\">{$client['name']}</span>
                        <span class=\"client-email\">" . ($client['email'] ?? 'No email') . "</span>
                    </div>
                </td>
                <td><span class=\"mono\">{$client['client_id']}</span></td>
                <td><span class=\"role-badge {$roleClass}\">{$role}</span></td>
                <td><span class=\"dim-text\">{$lastUsed}</span></td>
            </tr>";
        }

        $this->render("
        <div class=\"app-layout\">
            {$this->getSidebar('access')}

            <main class=\"main-content\">
                <header class=\"content-header\">
                    <div class=\"breadcrumb\">
                        <span>Access Control</span>
                    </div>
                    <div class=\"header-actions\">
                        <button class=\"btn btn-primary\">+ Add Client</button>
                    </div>
                </header>

                <div class=\"page-body\">
                    <div class=\"welcome-section\">
                        <h1>Access Control</h1>
                        <p>Manage API clients, machine identities, and their permissions within the organization.</p>
                    </div>

                    <div class=\"section-card\">
                        <table class=\"data-table\">
                            <thead>
                                <tr>
                                    <th>Client / Identity</th>
                                    <th>Client ID</th>
                                    <th>Role</th>
                                    <th>Last Used</th>
                                </tr>
                            </thead>
                            <tbody>{$clientsHtml}</tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>", $org['name']);
    }

    private function getSidebar($active = '')
    {
        $orgResult = $this->api->getOrganization();
        $org = $orgResult['status'] === 200 ? $orgResult['data'] : ['name' => 'Keymaster', 'slug' => 'keymaster'];
        
        $activeClass = fn($name) => $active === $name ? 'active' : '';

        return "
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
                    <a href=\"/\" class=\"{$activeClass('projects')}\">Projects</a>
                    <a href=\"/access-control\" class=\"{$activeClass('access')}\">Access Control</a>
                    <a href=\"/audit-logs\" class=\"{$activeClass('logs')}\">Audit Logs</a>
                    <a href=\"/settings\" class=\"{$activeClass('settings')}\">Organization Settings</a>
                    
                    <p class=\"nav-label\">Resources</p>
                    <a href=\"/credentials\" class=\"{$activeClass('vault')}\">Credentials Vault</a>
                </nav>
                <div class=\"sidebar-footer\">
                    <a href=\"/logout\" class=\"logout-btn\">Logout</a>
                </div>
            </aside>";
    }

    private function render($content, $orgName = 'Keymaster') {
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Control - ' . $orgName . '</title>
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
        body { font-family: "Inter", sans-serif; background: var(--bg); color: var(--text-main); height: 100vh; overflow: hidden; }
        .app-layout { display: flex; height: 100vh; }

        .sidebar { width: 260px; background: var(--sidebar-bg); border-right: 1px solid var(--border); display: flex; flex-direction: column; padding: 1.5rem; }
        .org-badge { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: rgba(255, 255, 255, 0.03); border-radius: 0.75rem; margin-bottom: 2rem; }
        .org-icon { width: 32px; height: 32px; background: var(--primary); border-radius: 6px; display: flex; align-items: center; justify-content: center; font-weight: 700; }
        .org-info { display: flex; flex-direction: column; }
        .org-name { font-weight: 600; font-size: 0.9rem; }
        .org-plan { font-size: 0.7rem; color: #fbbf24; font-weight: 600; text-transform: uppercase; }

        .nav-label { font-size: 0.7rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.05em; margin: 1.5rem 0 0.75rem 0.75rem; font-weight: 600; }
        .sidebar-nav a { display: block; padding: 0.6rem 0.75rem; color: var(--text-dim); text-decoration: none; font-size: 0.9rem; border-radius: 0.5rem; margin-bottom: 0.25rem; transition: all 0.2s; }
        .sidebar-nav a:hover { background: rgba(255, 255, 255, 0.05); color: var(--text-main); }
        .sidebar-nav a.active { background: rgba(59, 130, 246, 0.1); color: var(--primary); font-weight: 600; }

        .sidebar-footer { margin-top: auto; padding-top: 1.5rem; border-top: 1px solid var(--border); }
        .logout-btn { color: var(--text-dim); text-decoration: none; font-size: 0.9rem; }

        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; background: var(--bg); }
        .content-header { padding: 1rem 2.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: rgba(3, 7, 18, 0.5); backdrop-filter: blur(10px); position: sticky; top: 0; z-index: 10; }
        .breadcrumb { font-size: 0.9rem; color: var(--text-dim); }

        .page-body { padding: 3rem 2.5rem; max-width: 1200px; }
        .welcome-section { margin-bottom: 3rem; }
        .welcome-section h1 { font-size: 2rem; margin-bottom: 0.5rem; }
        .welcome-section p { color: var(--text-dim); }

        .section-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 1rem; padding: 1rem; }
        
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border); }
        .data-table th { color: var(--text-dim); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }
        
        .client-info { display: flex; flex-direction: column; }
        .client-name { font-weight: 600; font-size: 0.95rem; }
        .client-email { font-size: 0.8rem; color: var(--text-dim); }
        
        .role-badge { font-size: 0.7rem; font-weight: 600; padding: 0.2rem 0.5rem; border-radius: 4px; text-transform: uppercase; }
        .role-admin { background: rgba(59, 130, 246, 0.1); color: var(--primary); }
        .role-dev { background: rgba(255, 255, 255, 0.05); color: var(--text-dim); }

        .mono { font-family: "JetBrains Mono", monospace; font-size: 0.8rem; color: var(--text-dim); }
        .dim-text { color: var(--text-dim); font-size: 0.85rem; }
        .btn { padding: 0.6rem 1.25rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 0.85rem; border: none; }
        .btn-primary { background: var(--primary); color: white; }
    </style>
</head>
<body>
    ' . $content . '
</body>
</html>';
    }
}
