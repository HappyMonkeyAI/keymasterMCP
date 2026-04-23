<?php

namespace App\Controllers;

class ProjectsController
{
    private $api;
    
    public function __construct($api) {
        $this->api = $api;
    }

    public function index()
    {
        $result = $this->api->getProjects();
        $projects = $result['status'] === 200 ? $result['data'] : [];

        $rows = '';
        foreach ($projects as $p) {
            $desc = htmlspecialchars($p['description'] ?? '');
            $created = date('Y-m-d', strtotime($p['created_at']));
            $rows .= "
            <tr>
                <td><a href=\"/projects/{$p['id']}\" class=\"project-link\">{$p['name']}</a></td>
                <td><span class=\"dim-text\">{$desc}</span></td>
                <td><span class=\"mono dim-text\">{$created}</span></td>
                <td style=\"text-align: right;\">
                    <a href=\"/projects/{$p['id']}/edit\" class=\"btn btn-ghost btn-sm\">Edit</a>
                </td>
            </tr>";
        }

        $this->render("
        <div class=\"section-card\">
            <div class=\"card-header\">
                <h2 class=\"gradient-text\">Projects</h2>
                <a href=\"/projects/new\" class=\"btn btn-primary\">+ Create Project</a>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Project Name</th>
                        <th>Description</th>
                        <th>Created</th>
                        <th style=\"text-align: right;\">Actions</th>
                    </tr>
                </thead>
                <tbody>{$rows}</tbody>
            </table>
        </div>");
    }

    public function show($id)
    {
        $result = $this->api->getProject($id);
        if ($result['status'] !== 200) {
            header('Location: /projects');
            exit;
        }
        $project = $result['data'];

        $creds = '';
        foreach ($project['credentials'] as $c) {
            $creds .= "
            <div class=\"item-tag\">
                <span class=\"mono\">{$c}</span>
                <form method=\"post\" action=\"/projects/{$id}/credentials/{$c}\" style=\"display:inline;\">
                    <input type=\"hidden\" name=\"_METHOD\" value=\"DELETE\">
                    <button type=\"submit\" class=\"remove-btn\">&times;</button>
                </form>
            </div>";
        }

        $ips = '';
        foreach ($project['ips'] as $ip) {
            $ips .= "
            <div class=\"item-tag\">
                <span class=\"mono\">{$ip}</span>
                <form method=\"post\" action=\"/projects/{$id}/ips/{$ip}\" style=\"display:inline;\">
                    <input type=\"hidden\" name=\"_METHOD\" value=\"DELETE\">
                    <button type=\"submit\" class=\"remove-btn\">&times;</button>
                </form>
            </div>";
        }

        $this->render("
        <div class=\"project-detail-grid\">
            <div class=\"main-info\">
                <div class=\"section-card\">
                    <h2 class=\"gradient-text\" style=\"font-size: 1.5rem; margin-bottom: 0.5rem;\">{$project['name']}</h2>
                    <p class=\"dim-text\" style=\"margin-bottom: 2rem;\">" . htmlspecialchars($project['description'] ?? 'No description') . "</p>
                    
                    <div style=\"display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;\">
                        <div>
                            <h3 class=\"sub-title\">Credentials Access</h3>
                            <div class=\"tag-container\">{$creds}</div>
                            <form method=\"post\" action=\"/projects/{$id}/credentials\" class=\"inline-form\">
                                <input type=\"text\" name=\"service\" placeholder=\"Service (e.g. openai)\" required>
                                <button type=\"submit\" class=\"btn btn-primary btn-sm\">Add</button>
                            </form>
                        </div>
                        <div>
                            <h3 class=\"sub-title\">IP Whitelist</h3>
                            <div class=\"tag-container\">{$ips}</div>
                            <form method=\"post\" action=\"/projects/{$id}/ips\" class=\"inline-form\">
                                <input type=\"text\" name=\"ip_address\" placeholder=\"IP Address\" required>
                                <button type=\"submit\" class=\"btn btn-primary btn-sm\">Add</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <div class=\"side-info\">
                <div class=\"section-card\">
                    <h3 class=\"sub-title\">Project Settings</h3>
                    <div style=\"display: flex; flex-direction: column; gap: 0.75rem;\">
                        <a href=\"/projects/{$id}/edit\" class=\"btn btn-ghost\" style=\"width: 100%;\">Edit Project</a>
                        <form method=\"post\" action=\"/projects/{$id}\" style=\"width: 100%;\">
                            <input type=\"hidden\" name=\"_METHOD\" value=\"DELETE\">
                            <button type=\"submit\" class=\"btn btn-danger\" style=\"width: 100%;\" onclick=\"return confirm('Delete project?')\">Archive Project</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>");
    }

    public function new()
    {
        $this->render("
        <div class=\"glass-card\" style=\"max-width: 600px; margin: 0 auto;\">
            <h2 class=\"gradient-text\">Create New Project</h2>
            <p class=\"dim-text\" style=\"margin-bottom: 2rem;\">Projects isolate credentials and IP whitelists for different applications.</p>
            
            <form method=\"post\" action=\"/projects\">
                <div class=\"form-group\">
                    <label>Project Name</label>
                    <input type=\"text\" name=\"name\" placeholder=\"e.g. Production Web App\" required>
                </div>
                <div class=\"form-group\">
                    <label>Description</label>
                    <textarea name=\"description\" rows=\"3\" placeholder=\"What is this project for?\"></textarea>
                </div>
                <div style=\"display: flex; gap: 1rem; margin-top: 2rem;\">
                    <button type=\"submit\" class=\"btn btn-primary\">Create Project</button>
                    <a href=\"/projects\" class=\"btn btn-ghost\">Cancel</a>
                </div>
            </form>
        </div>");
    }

    public function create()
    {
        $data = $_POST;
        $this->api->createProject($data['name'], $data['description'] ?? '');
        header('Location: /projects');
        exit;
    }

    public function edit($id)
    {
        $result = $this->api->getProject($id);
        if ($result['status'] !== 200) {
            header('Location: /projects');
            exit;
        }
        $project = $result['data'];

        $this->render("
        <div class=\"glass-card\" style=\"max-width: 600px; margin: 0 auto;\">
            <h2 class=\"gradient-text\">Edit Project</h2>
            
            <form method=\"post\" action=\"/projects/{$id}\">
                <input type=\"hidden\" name=\"_METHOD\" value=\"PUT\">
                <div class=\"form-group\">
                    <label>Project Name</label>
                    <input type=\"text\" name=\"name\" value=\"" . htmlspecialchars($project['name']) . "\" required>
                </div>
                <div class=\"form-group\">
                    <label>Description</label>
                    <textarea name=\"description\" rows=\"3\">" . htmlspecialchars($project['description'] ?? '') . "</textarea>
                </div>
                <div style=\"display: flex; gap: 1rem; margin-top: 2rem;\">
                    <button type=\"submit\" class=\"btn btn-primary\">Save Changes</button>
                    <a href=\"/projects/{$id}\" class=\"btn btn-ghost\">Cancel</a>
                </div>
            </form>
        </div>");
    }

    public function update($id)
    {
        $data = $_POST;
        $this->api->updateProject($id, $data['name'], $data['description'] ?? '');
        header('Location: /projects/' . $id);
        exit;
    }

    public function delete($id)
    {
        $this->api->deleteProject($id);
        header('Location: /projects');
        exit;
    }

    public function addCredential($id)
    {
        $data = $_POST;
        $this->api->addProjectCredential($id, $data['service']);
        header('Location: /projects/' . $id);
        exit;
    }

    public function removeCredential($id, $service)
    {
        $this->api->removeProjectCredential($id, $service);
        header('Location: /projects/' . $id);
        exit;
    }

    public function addIp($id)
    {
        $data = $_POST;
        $this->api->addProjectIp($id, $data['ip_address']);
        header('Location: /projects/' . $id);
        exit;
    }

    public function removeIp($id, $ip)
    {
        $this->api->removeProjectIp($id, $ip);
        header('Location: /projects/' . $id);
        exit;
    }

    private function render($content) {
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects - Keymaster</title>
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
            --danger: #ef4444;
            --success: #22c55e;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: "Inter", sans-serif; 
            background: var(--bg); 
            color: var(--text-main); 
            min-height: 100vh;
            background-image: radial-gradient(circle at 100% 0%, rgba(59, 130, 246, 0.05) 0%, transparent 50%);
        }

        .header { backdrop-filter: blur(12px); background: rgba(3, 7, 18, 0.8); padding: 1.25rem 2.5rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; }
        .header h1 { font-size: 1.25rem; font-weight: 600; background: linear-gradient(to right, #fff, var(--text-dim)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .header nav a { color: var(--text-dim); text-decoration: none; margin-left: 2rem; font-size: 0.9rem; font-weight: 500; transition: color 0.2s; }
        .header nav a:hover { color: var(--text-main); }

        .container { padding: 3rem 2rem; max-width: 1200px; margin: 0 auto; }

        .section-card { background: var(--card-bg); backdrop-filter: blur(20px); border: 1px solid var(--border); border-radius: 1rem; padding: 2rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .gradient-text { font-weight: 600; color: #fff; }

        .btn { padding: 0.6rem 1.25rem; border-radius: 0.75rem; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 0.85rem; display: inline-flex; align-items: center; transition: all 0.2s; border: none; }
        .btn-primary { background: var(--primary); color: white; box-shadow: 0 0 20px var(--primary-glow); }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 0 30px var(--primary-glow); }
        .btn-ghost { background: rgba(255, 255, 255, 0.05); color: var(--text-main); border: 1px solid var(--border); }
        .btn-ghost:hover { background: rgba(255, 255, 255, 0.1); }
        .btn-danger { background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.2); }
        .btn-danger:hover { background: rgba(239, 68, 68, 0.2); }
        .btn-sm { padding: 0.4rem 0.8rem; font-size: 0.75rem; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1.25rem 1rem; text-align: left; border-bottom: 1px solid var(--border); }
        th { color: var(--text-dim); font-weight: 500; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; }
        tr:hover { background: rgba(255, 255, 255, 0.02); }
        
        .mono { font-family: "JetBrains Mono", monospace; font-size: 0.85rem; }
        .dim-text { color: var(--text-dim); font-size: 0.9rem; }
        .project-link { color: var(--primary); text-decoration: none; font-weight: 600; transition: color 0.2s; }
        .project-link:hover { color: #60a5fa; }

        .project-detail-grid { display: grid; grid-template-columns: 1fr 300px; gap: 2rem; }
        .sub-title { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-dim); margin-bottom: 1.25rem; }
        
        .tag-container { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1.5rem; }
        .item-tag { background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border); padding: 0.4rem 0.75rem; border-radius: 0.5rem; display: flex; align-items: center; gap: 0.5rem; }
        .remove-btn { background: none; border: none; color: var(--text-dim); cursor: pointer; font-size: 1.1rem; line-height: 1; padding: 0 0.2rem; }
        .remove-btn:hover { color: var(--danger); }

        .inline-form { display: flex; gap: 0.5rem; }
        .inline-form input { background: rgba(0,0,0,0.3); border: 1px solid var(--border); border-radius: 0.5rem; padding: 0.4rem 0.75rem; color: white; font-size: 0.85rem; flex: 1; }
        
        .glass-card { background: var(--card-bg); backdrop-filter: blur(20px); border: 1px solid var(--border); border-radius: 1.5rem; padding: 2.5rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; color: var(--text-dim); margin-bottom: 0.6rem; font-size: 0.9rem; font-weight: 500; }
        .form-group input, .form-group textarea { width: 100%; padding: 0.85rem; background: rgba(0,0,0,0.3); border: 1px solid var(--border); border-radius: 0.75rem; color: white; font-family: inherit; }
        .form-group input:focus { outline: none; border-color: var(--primary); }

        @media (max-width: 900px) { .project-detail-grid { grid-template-columns: 1fr; } }
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
