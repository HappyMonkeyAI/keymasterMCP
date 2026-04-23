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
        $orgResult = $this->api->getOrganization();
        $result = $this->api->getProjects();
        
        $org = $orgResult['status'] === 200 ? $orgResult['data'] : ['name' => 'Keymaster', 'slug' => 'keymaster'];
        $projects = $result['status'] === 200 ? $result['data'] : [];

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

        $this->render("
        <div class=\"app-layout\">
            {$this->getSidebar('projects')}

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
                        <p>Manage your infrastructure security across all environments.</p>
                    </div>

                    <div class=\"project-grid\">
                        {$projectsHtml}
                    </div>
                </div>
            </main>
        </div>", $org['name']);
    }

    public function show($id)
    {
        $orgResult = $this->api->getOrganization();
        $result = $this->api->getProject($id);
        
        $org = $orgResult['status'] === 200 ? $orgResult['data'] : ['name' => 'Keymaster', 'slug' => 'keymaster'];
        if ($result['status'] !== 200) {
            header('Location: /');
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
        <div class=\"app-layout\">
            {$this->getSidebar('projects')}

            <main class=\"main-content\">
                <header class=\"content-header\">
                    <div class=\"breadcrumb\">
                        <a href=\"/\" style=\"color: var(--text-dim); text-decoration: none;\">Projects</a>
                        <span style=\"margin: 0 0.5rem;\">/</span>
                        <span>{$project['name']}</span>
                    </div>
                </header>

                <div class=\"page-body\">
                    <div class=\"project-header-section\">
                        <div style=\"display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem;\">
                            <div>
                                <span class=\"project-type\">" . ucfirst($project['type'] ?? 'secrets') . "</span>
                                <h1 style=\"font-size: 2rem;\">{$project['name']}</h1>
                                <p class=\"dim-text\">" . htmlspecialchars($project['description'] ?? '') . "</p>
                            </div>
                            <div class=\"header-actions\">
                                <a href=\"/projects/{$id}/edit\" class=\"btn btn-ghost\">Edit Settings</a>
                            </div>
                        </div>
                    </div>

                    <div style=\"display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;\">
                        <div class=\"section-card\">
                            <h3 class=\"sub-title\">Credentials Access</h3>
                            <div class=\"tag-container\">{$creds}</div>
                            <form method=\"post\" action=\"/projects/{$id}/credentials\" class=\"inline-form\">
                                <input type=\"text\" name=\"service\" placeholder=\"Service (e.g. openai)\" required>
                                <button type=\"submit\" class=\"btn btn-primary\">Add</button>
                            </form>
                        </div>
                        <div class=\"section-card\">
                            <h3 class=\"sub-title\">IP Whitelist</h3>
                            <div class=\"tag-container\">{$ips}</div>
                            <form method=\"post\" action=\"/projects/{$id}/ips\" class=\"inline-form\">
                                <input type=\"text\" name=\"ip_address\" placeholder=\"IP Address\" required>
                                <button type=\"submit\" class=\"btn btn-primary\">Add</button>
                            </form>
                        </div>
                    </div>

                    <div class=\"section-card\" style=\"margin-top: 2rem; border-color: rgba(239, 68, 68, 0.1);\">
                        <h3 class=\"sub-title\" style=\"color: var(--danger);\">Danger Zone</h3>
                        <form method=\"post\" action=\"/projects/{$id}\">
                            <input type=\"hidden\" name=\"_METHOD\" value=\"DELETE\">
                            <button type=\"submit\" class=\"btn btn-danger\" onclick=\"return confirm('Permanently delete this project?')\">Delete Project</button>
                        </form>
                    </div>
                </div>
            </main>
        </div>", $org['name']);
    }

    public function new()
    {
        $orgResult = $this->api->getOrganization();
        $org = $orgResult['status'] === 200 ? $orgResult['data'] : ['name' => 'Keymaster', 'slug' => 'keymaster'];
        
        $this->render("
        <div class=\"app-layout\">
            {$this->getSidebar('projects')}

            <main class=\"main-content\">
                <header class=\"content-header\">
                    <div class=\"breadcrumb\">
                        <a href=\"/\" style=\"color: var(--text-dim); text-decoration: none;\">Projects</a>
                        <span style=\"margin: 0 0.5rem;\">/</span>
                        <span>New Project</span>
                    </div>
                </header>

                <div class=\"page-body\">
                    <div class=\"section-card\" style=\"max-width: 600px; margin: 0 auto;\">
                        <h2 style=\"margin-bottom: 1.5rem;\">Create New Project</h2>
                        <form method=\"post\" action=\"/projects\">
                            <div class=\"form-group\">
                                <label>Project Name</label>
                                <input type=\"text\" name=\"name\" placeholder=\"e.g. Aura.AI Chatbot\" required>
                            </div>
                            <div class=\"form-group\">
                                <label>Project Type</label>
                                <select name=\"type\" style=\"width: 100%; padding: 0.85rem; background: rgba(0,0,0,0.3); border: 1px solid var(--border); border-radius: 0.75rem; color: white;\">
                                    <option value=\"secrets\">Secrets Management</option>
                                    <option value=\"pki\">PKI / Certificates</option>
                                    <option value=\"kms\">KMS / Keys</option>
                                    <option value=\"ssh\">SSH Access</option>
                                </select>
                            </div>
                            <div class=\"form-group\">
                                <label>Description</label>
                                <textarea name=\"description\" rows=\"3\" placeholder=\"Describe the purpose of this project\"></textarea>
                            </div>
                            <div style=\"display: flex; gap: 1rem; margin-top: 2rem;\">
                                <button type=\"submit\" class=\"btn btn-primary\">Create Project</button>
                                <a href=\"/\" class=\"btn btn-ghost\">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>", $org['name']);
    }

    public function create()
    {
        $data = $_POST;
        $this->api->createProject($data['name'], $data['description'] ?? '', $data['type'] ?? 'secrets');
        header('Location: /');
        exit;
    }

    public function edit($id)
    {
        $orgResult = $this->api->getOrganization();
        $result = $this->api->getProject($id);
        
        $org = $orgResult['status'] === 200 ? $orgResult['data'] : ['name' => 'Keymaster', 'slug' => 'keymaster'];
        if ($result['status'] !== 200) {
            header('Location: /');
            exit;
        }
        $project = $result['data'];

        $this->render("
        <div class=\"app-layout\">
            {$this->getSidebar('projects')}

            <main class=\"main-content\">
                <header class=\"content-header\">
                    <div class=\"breadcrumb\">
                        <a href=\"/\" style=\"color: var(--text-dim); text-decoration: none;\">Projects</a>
                        <span style=\"margin: 0 0.5rem;\">/</span>
                        <a href=\"/projects/{$id}\" style=\"color: var(--text-dim); text-decoration: none;\">{$project['name']}</a>
                        <span style=\"margin: 0 0.5rem;\">/</span>
                        <span>Settings</span>
                    </div>
                </header>

                <div class=\"page-body\">
                    <div class=\"section-card\" style=\"max-width: 600px; margin: 0 auto;\">
                        <h2 style=\"margin-bottom: 1.5rem;\">Edit Project</h2>
                        <form method=\"post\" action=\"/projects/{$id}\">
                            <input type=\"hidden\" name=\"_METHOD\" value=\"PUT\">
                            <div class=\"form-group\">
                                <label>Project Name</label>
                                <input type=\"text\" name=\"name\" value=\"" . htmlspecialchars($project['name']) . "\" required>
                            </div>
                            <div class=\"form-group\">
                                <label>Project Type</label>
                                <select name=\"type\" style=\"width: 100%; padding: 0.85rem; background: rgba(0,0,0,0.3); border: 1px solid var(--border); border-radius: 0.75rem; color: white;\">
                                    <option value=\"secrets\" " . ($project['type'] === 'secrets' ? 'selected' : '') . ">Secrets Management</option>
                                    <option value=\"pki\" " . ($project['type'] === 'pki' ? 'selected' : '') . ">PKI / Certificates</option>
                                    <option value=\"kms\" " . ($project['type'] === 'kms' ? 'selected' : '') . ">KMS / Keys</option>
                                    <option value=\"ssh\" " . ($project['type'] === 'ssh' ? 'selected' : '') . ">SSH Access</option>
                                </select>
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
                    </div>
                </div>
            </main>
        </div>", $org['name']);
    }

    public function update($id)
    {
        $data = $_POST;
        $this->api->updateProject($id, $data['name'], $data['description'] ?? '', $data['type'] ?? 'secrets');
        header('Location: /projects/' . $id);
        exit;
    }

    public function delete($id)
    {
        $this->api->deleteProject($id);
        header('Location: /');
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

    private function getProjectIcon($type) {
        return match($type) {
            'secrets' => '🔒',
            'pki' => '📜',
            'kms' => '🔑',
            'ssh' => '💻',
            default => '📁'
        };
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
    <title>Projects - ' . $orgName . '</title>
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

        .project-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; }
        .project-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 1rem; padding: 1.5rem; text-decoration: none; color: inherit; display: flex; gap: 1rem; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .project-card:hover { border-color: var(--primary); background: rgba(59, 130, 246, 0.05); transform: translateY(-2px); }
        .project-card-icon { font-size: 1.5rem; padding-top: 0.25rem; }
        .project-card-content h3 { font-size: 1.1rem; margin-bottom: 0.25rem; }
        .project-type { font-size: 0.75rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem; display: block; }
        .project-card-content p { font-size: 0.9rem; color: var(--text-dim); line-height: 1.5; }

        .section-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 1rem; padding: 1.5rem; }
        .sub-title { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-dim); margin-bottom: 1.25rem; font-weight: 600; }
        
        .tag-container { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1.5rem; }
        .item-tag { background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border); padding: 0.4rem 0.75rem; border-radius: 0.5rem; display: flex; align-items: center; gap: 0.5rem; }
        .remove-btn { background: none; border: none; color: var(--text-dim); cursor: pointer; font-size: 1.1rem; line-height: 1; padding: 0 0.2rem; }
        .remove-btn:hover { color: var(--danger); }

        .inline-form { display: flex; gap: 0.5rem; }
        .inline-form input { background: rgba(0,0,0,0.3); border: 1px solid var(--border); border-radius: 0.5rem; padding: 0.4rem 0.75rem; color: white; font-size: 0.85rem; flex: 1; }
        
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; color: var(--text-dim); margin-bottom: 0.6rem; font-size: 0.9rem; font-weight: 500; }
        .form-group input, .form-group textarea { width: 100%; padding: 0.85rem; background: rgba(0,0,0,0.3); border: 1px solid var(--border); border-radius: 0.75rem; color: white; font-family: inherit; }
        .form-group input:focus { outline: none; border-color: var(--primary); }

        .btn { padding: 0.6rem 1.25rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 0.85rem; border: none; display: inline-flex; align-items: center; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-ghost { background: rgba(255, 255, 255, 0.05); color: var(--text-main); border: 1px solid var(--border); }
        .btn-ghost:hover { background: rgba(255, 255, 255, 0.1); }
        .btn-danger { background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.2); }
        .btn-danger:hover { background: rgba(239, 68, 68, 0.2); }
        .mono { font-family: "JetBrains Mono", monospace; font-size: 0.85rem; }
        .dim-text { color: var(--text-dim); font-size: 0.9rem; }
    </style>
</head>
<body>
    ' . $content . '
</body>
</html>';
    }
}
