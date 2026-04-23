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
        $servicesResult = $this->api->getServices();
        $groupsResult = $this->api->getCredentialGroups();
        
        $services = $servicesResult['status'] === 200 ? $servicesResult['data'] : [];
        $groups = $groupsResult['status'] === 200 ? $groupsResult['data'] : [];

        // Organize services by group
        $groupedServices = [];
        foreach ($services as $s) {
            $gn = $s['group_name'] ?? 'Uncategorized';
            $groupedServices[$gn][] = $s;
        }

        $content = $this->renderGroups($groupedServices, $groups);

        $this->render($content);
    }

    public function new()
    {
        $groupsResult = $this->api->getCredentialGroups();
        $groups = $groupsResult['status'] === 200 ? $groupsResult['data'] : [];
        
        $groupOptions = '<option value="">None</option>';
        foreach ($groups as $g) {
            $groupOptions .= "<option value=\"{$g['id']}\">{$g['name']}</option>";
        }

        $this->render("
        <div class=\"glass-card\">
            <h2 class=\"gradient-text\">Add API Credential</h2>
            <p style=\"color: #94a3b8; margin-bottom: 2rem;\">Securely store a new API key in the encrypted vault.</p>
            
            <form method=\"post\" action=\"/credentials\">
                <div class=\"form-grid\">
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
                        <select name=\"group_id\">{$groupOptions}</select>
                    </div>
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
        </div>");
    }

    public function create()
    {
        $data = $_POST;
        
        // 1. Store the secret in the vault
        $vaultResult = $this->api->addKey($data['service'], $data['api_key']);
        
        if ($vaultResult['status'] === 201) {
            // 2. Register metadata if provided
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
        $this->render("
        <div class=\"glass-card\">
            <h2 class=\"gradient-text\">Update Credential - {$service}</h2>
            <p style=\"color: #94a3b8; margin-bottom: 2rem;\">Rotation will overwrite the existing secret in the encrypted vault.</p>
            
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
        </div>");
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
        <div style=\"display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;\">
            <div>
                <h2 class=\"gradient-text\" style=\"font-size: 1.75rem;\">Credentials Vault</h2>
                <p style=\"color: #94a3b8;\">Encrypted secrets grouped by project or service type.</p>
            </div>
            <div style=\"display: flex; gap: 1rem;\">
                <a href=\"#\" onclick=\"document.getElementById('groupModal').style.display='flex'\" class=\"btn btn-ghost\">+ New Group</a>
                <a href=\"/credentials/new\" class=\"btn btn-primary\">+ Add Credential</a>
            </div>
        </div>

        <div id=\"groupModal\" class=\"modal-overlay\" style=\"display: none;\">
            <div class=\"glass-card\" style=\"max-width: 400px; width: 100%;\">
                <h3>Create New Group</h3>
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
            $html .= "<div class=\"glass-card\" style=\"text-align: center; padding: 4rem;\">
                <p style=\"color: #94a3b8;\">No credentials found in the vault.</p>
                <a href=\"/credentials/new\" class=\"btn btn-primary\" style=\"margin-top: 1rem;\">Add your first key</a>
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

    private function render($content) {
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credentials - Keymaster MCP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #030712;
            --card-bg: rgba(17, 24, 39, 0.7);
            --border: rgba(255, 255, 255, 0.1);
            --primary: #3b82f6;
            --primary-glow: rgba(59, 130, 246, 0.5);
            --text-main: #f8fafc;
            --text-dim: #94a3b8;
            --danger: #ef4444;
            --success: #22c55e;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: "Outfit", sans-serif; 
            background: var(--bg); 
            color: var(--text-main); 
            min-height: 100vh;
            background-image: 
                radial-gradient(circle at 0% 0%, rgba(59, 130, 246, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 100% 100%, rgba(147, 51, 234, 0.15) 0%, transparent 50%);
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
        .header h1 { font-size: 1.5rem; font-weight: 600; letter-spacing: -0.025em; }
        .header nav a { color: var(--text-dim); text-decoration: none; margin-left: 2rem; font-size: 0.95rem; transition: color 0.2s; }
        .header nav a:hover { color: var(--text-main); }

        .container { padding: 3rem 2rem; max-width: 1200px; margin: 0 auto; }

        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: 1.5rem;
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .gradient-text {
            background: linear-gradient(135deg, #fff 0%, #94a3b8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 600;
        }

        .btn { 
            padding: 0.75rem 1.5rem; 
            border-radius: 0.75rem; 
            font-weight: 600; 
            cursor: pointer; 
            text-decoration: none; 
            font-size: 0.9rem; 
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
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 0 30px var(--primary-glow); }
        .btn-ghost { background: var(--border); color: var(--text-main); }
        .btn-ghost:hover { background: rgba(255, 255, 255, 0.15); }

        .credential-group { margin-bottom: 3rem; }
        .group-header { 
            display: flex; 
            align-items: center; 
            gap: 1rem; 
            margin-bottom: 1.25rem; 
            font-size: 1.1rem; 
            font-weight: 600;
            color: var(--text-dim);
        }
        .badge { font-size: 0.7rem; background: var(--border); padding: 0.2rem 0.6rem; border-radius: 2rem; text-transform: uppercase; letter-spacing: 0.05em; }

        .key-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); 
            gap: 1.5rem; 
        }

        .key-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 1.5rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .key-card:hover {
            border-color: var(--primary);
            background: rgba(59, 130, 246, 0.05);
            transform: scale(1.02);
        }

        .key-top { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; }
        .status-dot.configured { background: var(--success); box-shadow: 0 0 10px var(--success); }
        .status-dot.missing { background: var(--danger); }
        .key-top h4 { font-size: 1.1rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .key-desc { color: var(--text-dim); font-size: 0.9rem; line-height: 1.5; margin-bottom: 1.5rem; min-height: 2.7rem; }

        .key-actions { display: flex; gap: 1rem; border-top: 1px solid var(--border); padding-top: 1rem; font-size: 0.85rem; }
        .key-actions a { color: var(--primary); text-decoration: none; font-weight: 600; }
        .key-actions button { background: none; border: none; font-weight: 600; cursor: pointer; font-family: inherit; }
        .text-danger { color: var(--danger) !important; }

        /* Form styling */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; color: var(--text-dim); margin-bottom: 0.6rem; font-size: 0.9rem; font-weight: 500; }
        .form-group input, .form-group textarea, .form-group select { 
            width: 100%; 
            padding: 1rem; 
            background: rgba(0, 0, 0, 0.3); 
            border: 1px solid var(--border); 
            border-radius: 0.75rem; 
            color: white; 
            font-family: inherit;
            transition: border-color 0.2s;
        }
        .form-group input:focus { outline: none; border-color: var(--primary); }

        .modal-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(4px);
            z-index: 1000;
            display: flex; align-items: center; justify-content: center;
        }

        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .key-grid { grid-template-columns: 1fr; }
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
    <div class="container">
        ' . $content . '
    </div>
</body>
</html>';
    }
}
