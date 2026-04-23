<?php

namespace App\Controllers;

class CredentialsController
{
    private $api;
    
    public function __construct($api) {
        $this->api = $api;
    }

    public function index()
    {
        $orgResult = $this->api->getOrganization();
        $servicesResult = $this->api->getServices();
        $groupsResult = $this->api->getCredentialGroups();
        
        $org = $orgResult['status'] === 200 ? $orgResult['data'] : ['name' => 'Keymaster', 'slug' => 'keymaster'];
        $services = $servicesResult['status'] === 200 ? $servicesResult['data'] : [];
        $groups = $groupsResult['status'] === 200 ? $groupsResult['data'] : [];

        // Organize services by group
        $groupedServices = [];
        foreach ($services as $s) {
            $gn = $s['group_name'] ?? 'Uncategorized';
            $groupedServices[$gn][] = $s;
        }

        $vaultContent = $this->renderGroups($groupedServices, $groups);

        $this->render("
        <div class=\"app-layout\">
            {$this->getSidebar('vault')}

            <main class=\"main-content\">
                <header class=\"content-header\">
                    <div class=\"breadcrumb\">
                        <span>Credentials Vault</span>
                    </div>
                    <div class=\"header-actions\">
                         <a href=\"#\" onclick=\"document.getElementById('groupModal').style.display='flex'\" class=\"btn btn-ghost\" style=\"margin-right: 1rem;\">+ New Group</a>
                        <a href=\"/credentials/new\" class=\"btn btn-primary\">+ Add Credential</a>
                    </div>
                </header>

                <div class=\"page-body\">
                    {$vaultContent}
                </div>
            </main>
        </div>", $org['name']);
    }

    public function new()
    {
        $orgResult = $this->api->getOrganization();
        $groupsResult = $this->api->getCredentialGroups();
        
        $org = $orgResult['status'] === 200 ? $orgResult['data'] : ['name' => 'Keymaster', 'slug' => 'keymaster'];
        $groups = $groupsResult['status'] === 200 ? $groupsResult['data'] : [];
        
        $groupOptions = '<option value="">None</option>';
        foreach ($groups as $g) {
            $groupOptions .= "<option value=\"{$g['id']}\">{$g['name']}</option>";
        }

        $this->render("
        <div class=\"app-layout\">
            {$this->getSidebar('vault')}

            <main class=\"main-content\">
                <header class=\"content-header\">
                    <div class=\"breadcrumb\">
                        <a href=\"/credentials\" style=\"color: var(--text-dim); text-decoration: none;\">Vault</a>
                        <span style=\"margin: 0 0.5rem;\">/</span>
                        <span>New Credential</span>
                    </div>
                </header>

                <div class=\"page-body\">
                    <div class=\"section-card\" style=\"max-width: 600px; margin: 0 auto;\">
                        <h2 style=\"margin-bottom: 1.5rem;\">Add API Credential</h2>
                        <form method=\"post\" action=\"/credentials\">
                            <div class=\"form-group\">
                                <label>Service Identifier</label>
                                <input type=\"text\" name=\"service\" placeholder=\"e.g. stripe, openai-prod\" required>
                            </div>
                            <div class=\"form-group\">
                                <label>Display Name (Optional)</label>
                                <input type=\"text\" name=\"display_name\" placeholder=\"e.g. Stripe Production\">
                            </div>
                            <div class=\"form-group\">
                                <label>Group</label>
                                <select name=\"group_id\" style=\"width: 100%; padding: 0.85rem; background: rgba(0,0,0,0.3); border: 1px solid var(--border); border-radius: 0.75rem; color: white;\">{$groupOptions}</select>
                            </div>
                            <div class=\"form-group\">
                                <label>API Key / Secret</label>
                                <input type=\"password\" name=\"api_key\" placeholder=\"••••••••••••••••\" required>
                            </div>
                            <div class=\"form-group\">
                                <label>Description</label>
                                <textarea name=\"description\" rows=\"2\" placeholder=\"What is this key used for?\"></textarea>
                            </div>
                            <div style=\"margin-top: 2rem; display: flex; gap: 1rem;\">
                                <button type=\"submit\" class=\"btn btn-primary\">Store in Vault</button>
                                <a href=\"/credentials\" class=\"btn btn-ghost\">Cancel</a>
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
        $vaultResult = $this->api->addKey($data['service'], $data['api_key']);
        
        if ($vaultResult['status'] === 201) {
            $this->api->registerCredential(
                $data['service'],
                $data['display_name'] ?? $data['service'],
                !empty($data['group_id']) ? (int)$data['group_id'] : null,
                $data['description'] ?? null
            );
            
            header('Location: /credentials');
            exit;
        }
        
        echo "Error adding key: " . ($vaultResult['data']['detail'] ?? 'Unknown error');
    }

    public function createGroup()
    {
        $data = $_POST;
        $this->api->createCredentialGroup($data['name'], $data['description'] ?? '');
        header('Location: /credentials');
        exit;
    }

    public function edit($service)
    {
        $orgResult = $this->api->getOrganization();
        $org = $orgResult['status'] === 200 ? $orgResult['data'] : ['name' => 'Keymaster', 'slug' => 'keymaster'];

        $this->render("
        <div class=\"app-layout\">
            {$this->getSidebar('vault')}

            <main class=\"main-content\">
                <header class=\"content-header\">
                    <div class=\"breadcrumb\">
                        <a href=\"/credentials\" style=\"color: var(--text-dim); text-decoration: none;\">Vault</a>
                        <span style=\"margin: 0 0.5rem;\">/</span>
                        <span>Rotate {$service}</span>
                    </div>
                </header>

                <div class=\"page-body\">
                    <div class=\"section-card\" style=\"max-width: 600px; margin: 0 auto;\">
                        <h2 style=\"margin-bottom: 1rem;\">Update Credential</h2>
                        <p class=\"dim-text\" style=\"margin-bottom: 2rem;\">Rotation will overwrite the existing secret in the encrypted vault.</p>
                        
                        <form method=\"post\" action=\"/credentials/{$service}\">
                            <input type=\"hidden\" name=\"_METHOD\" value=\"PUT\">
                            <div class=\"form-group\">
                                <label>New API Key / Secret</label>
                                <input type=\"password\" name=\"api_key\" placeholder=\"••••••••••••••••\" required autofocus>
                            </div>
                            <div style=\"margin-top: 2rem; display: flex; gap: 1rem;\">
                                <button type=\"submit\" class=\"btn btn-primary\">Update Secret</button>
                                <a href=\"/credentials\" class=\"btn btn-ghost\">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>", $org['name']);
    }

    public function update($service)
    {
        $data = $_POST;
        $result = $this->api->rotateKey($service, $data['api_key']);
        
        if ($result['status'] === 200) {
            header('Location: /credentials');
            exit;
        }
        
        echo "Error updating key";
    }

    public function delete($service)
    {
        $this->api->deleteKey($service);
        header('Location: /credentials');
        exit;
    }

    private function renderGroups($groupedServices, $groups)
    {
        $html = "
        <div class=\"welcome-section\">
            <h1>Credentials Vault</h1>
            <p>Securely manage your API keys, tokens, and infrastructure secrets.</p>
        </div>

        <div id=\"groupModal\" class=\"modal-overlay\" style=\"display: none;\">
            <div class=\"section-card\" style=\"max-width: 400px; width: 100%;\">
                <h3 style=\"margin-bottom: 1.5rem;\">Create New Group</h3>
                <form method=\"post\" action=\"/credentials/groups\">
                    <div class=\"form-group\"><label>Group Name</label><input type=\"text\" name=\"name\" required></div>
                    <div class=\"form-group\"><label>Description</label><input type=\"text\" name=\"description\"></div>
                    <div style=\"display: flex; gap: 1rem; margin-top: 1rem;\">
                        <button type=\"submit\" class=\"btn btn-primary\">Create</button>
                        <button type=\"button\" onclick=\"document.getElementById('groupModal').style.display='none'\" class=\"btn btn-ghost\">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
        ";

        if (empty($groupedServices)) {
            $html .= "<div class=\"section-card\" style=\"text-align: center; padding: 4rem;\">
                <p class=\"dim-text\" style=\"margin-bottom: 1.5rem;\">No credentials found in the vault.</p>
                <a href=\"/credentials/new\" class=\"btn btn-primary\">Add your first key</a>
            </div>";
            return $html;
        }

        foreach ($groupedServices as $groupName => $services) {
            $html .= "
            <div class=\"credential-group\">
                <div class=\"group-header\">
                    <span>{$groupName}</span>
                    <span class=\"badge\">" . count($services) . " keys</span>
                </div>
                <div class=\"key-grid\">";
            
            foreach ($services as $s) {
                $status = $s['configured'] ? 'configured' : 'missing';
                $displayName = ($s['display_name'] ?? null) ?: $s['name'];
                $desc = ($s['description'] ?? null) ?: 'No description provided.';
                
                $html .= "
                <div class=\"key-card\">
                    <div class=\"key-top\">
                        <div class=\"status-dot {$status}\"></div>
                        <h4 title=\"{$s['name']}\">{$displayName}</h4>
                    </div>
                    <p class=\"key-desc\">{$desc}</p>
                    <div class=\"key-actions\">
                        <a href=\"/credentials/{$s['name']}/edit\">Rotate</a>
                        <form method=\"post\" action=\"/credentials/{$s['name']}\" style=\"display:inline;\">
                            <input type=\"hidden\" name=\"_METHOD\" value=\"DELETE\">
                            <button type=\"submit\" class=\"text-danger\" onclick=\"return confirm('Purge secret from vault?')\">Delete</button>
                        </form>
                    </div>
                </div>";
            }
            
            $html .= "</div></div>";
        }

        return $html;
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
    <title>Vault - ' . $orgName . '</title>
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

        .section-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 1rem; padding: 1.5rem; }
        
        .credential-group { margin-bottom: 3rem; }
        .group-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.25rem; font-size: 1rem; font-weight: 600; color: var(--text-dim); }
        .badge { font-size: 0.7rem; background: rgba(255, 255, 255, 0.05); padding: 0.2rem 0.6rem; border-radius: 2rem; text-transform: uppercase; letter-spacing: 0.05em; }

        .key-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.5rem; }
        .key-card { background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border); border-radius: 1rem; padding: 1.5rem; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .key-card:hover { border-color: var(--primary); background: rgba(59, 130, 246, 0.05); transform: translateY(-2px); }
        
        .key-top { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; }
        .status-dot.configured { background: var(--success); box-shadow: 0 0 10px var(--success); }
        .status-dot.missing { background: var(--danger); }
        .key-top h4 { font-size: 1.1rem; font-weight: 600; }

        .key-desc { color: var(--text-dim); font-size: 0.85rem; line-height: 1.5; margin-bottom: 1.5rem; }
        .key-actions { display: flex; gap: 1rem; border-top: 1px solid var(--border); padding-top: 1rem; font-size: 0.85rem; }
        .key-actions a { color: var(--primary); text-decoration: none; font-weight: 600; }
        .key-actions button { background: none; border: none; font-weight: 600; cursor: pointer; color: var(--danger); }

        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.8); backdrop-filter: blur(4px); z-index: 1000; display: flex; align-items: center; justify-content: center; }

        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; color: var(--text-dim); margin-bottom: 0.6rem; font-size: 0.9rem; font-weight: 500; }
        .form-group input, .form-group textarea { width: 100%; padding: 0.85rem; background: rgba(0,0,0,0.3); border: 1px solid var(--border); border-radius: 0.75rem; color: white; font-family: inherit; }
        .form-group input:focus { outline: none; border-color: var(--primary); }

        .btn { padding: 0.6rem 1.25rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 0.85rem; border: none; display: inline-flex; align-items: center; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-ghost { background: rgba(255, 255, 255, 0.05); color: var(--text-main); border: 1px solid var(--border); }
        .dim-text { color: var(--text-dim); font-size: 0.9rem; }
    </style>
</head>
<body>
    ' . $content . '
</body>
</html>';
    }
}
