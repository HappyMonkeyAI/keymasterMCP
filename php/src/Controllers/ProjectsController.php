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
        $this->render('projects_list', ['projects' => $projects]);
    }

    public function new()
    {
        $this->render('projects_form', []);
    }

    public function create()
    {
        $data = $_POST;
        $result = $this->api->createProject($data['name'] ?? '', $data['description'] ?? '');
        
        if ($result['status'] === 201) {
            header('Location: /projects/' . $result['data']['id']);
            exit;
        }
        
        $this->render('projects_form', ['error' => 'Error creating project']);
    }

    public function show($id)
    {
        $projectResult = $this->api->getProject($id);
        $servicesResult = $this->api->getServices();
        
        if ($projectResult['status'] !== 200) {
            http_response_code(404);
            echo "Project not found";
            exit;
        }
        
        $project = $projectResult['data'];
        $allServices = $servicesResult['status'] === 200 ? $servicesResult['data'] : [];
        
        $this->render('projects_detail', [
            'project' => $project, 
            'services' => $allServices
        ]);
    }

    public function edit($id)
    {
        $result = $this->api->getProject($id);
        
        if ($result['status'] !== 200) {
            http_response_code(404);
            echo "Project not found";
            exit;
        }
        
        $this->render('projects_form', ['project' => $result['data']]);
    }

    public function update($id)
    {
        $data = $_POST;
        $result = $this->api->updateProject($id, $data['name'] ?? '', $data['description'] ?? '');
        
        if ($result['status'] === 200) {
            header('Location: /projects/' . $id);
            exit;
        }
        
        $this->render('projects_form', ['error' => 'Error updating project']);
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
        $this->api->removeProjectIp($id, urldecode($ip));
        header('Location: /projects/' . $id);
        exit;
    }

    private function render(string $template, array $data): void
    {
        $nav = '<a href="/">Dashboard</a> <a href="/credentials">Credentials</a> <a href="/projects">Projects</a> <a href="/logout">Logout</a>';
        
        $content = '';
        switch ($template) {
            case 'projects_list':
                $rows = '';
                foreach ($data['projects'] as $p) {
                    $desc = htmlspecialchars($p['description'] ?? '-');
                    $rows .= "<tr><td><a href=\"/projects/{$p['id']}\">{$p['name']}</a></td><td>{$desc}</td><td>{$p['created_at']}</td></tr>";
                }
                $content = "<h2>Projects <a href=\"/projects/new\" class=\"btn\">New Project</a></h2>
                <table><thead><tr><th>Name</th><th>Description</th><th>Created</th></tr></thead><tbody>{$rows}</tbody></table>";
                break;
                
            case 'projects_form':
                $name = $data['project']['name'] ?? '';
                $desc = $data['project']['description'] ?? '';
                $id = $data['project']['id'] ?? '';
                $action = $id ? "/projects/{$id}" : '/projects';
                $title = $id ? 'Edit' : 'New';
                $content = "<h2>{$title} Project</h2>";
                if (isset($data['error'])) {
                    $content .= "<p style=\"color:#ef4444;margin-bottom:1rem;\">{$data['error']}</p>";
                }
                $content .= "
                <form method=\"post\" action=\"{$action}\" style=\"max-width:500px;\">
                    <div class=\"form-group\"><label>Name</label><input type=\"text\" name=\"name\" value=\"{$name}\" required></div>
                    <div class=\"form-group\"><label>Description</label><textarea name=\"description\" rows=\"3\">{$desc}</textarea></div>
                    <button type=\"submit\" class=\"btn\">Save</button>
                    <a href=\"/projects\" class=\"btn\" style=\"background:#64748b;\">Cancel</a>
                </form>";
                break;
                
            case 'projects_detail':
                $p = $data['project'];
                $credRows = '';
                foreach ($p['credentials'] ?? [] as $cred) {
                    $credRows .= "<tr><td>{$cred}</td><td>
                        <form method=\"post\" action=\"/projects/{$p['id']}/credentials/" . urlencode($cred) . "\" style=\"display:inline;\">
                            <input type=\"hidden\" name=\"_METHOD\" value=\"DELETE\">
                            <button type=\"submit\" style=\"background:none;border:none;color:#ef4444;cursor:pointer;padding:0;font:inherit;\">Remove</button>
                        </form>
                    </td></tr>";
                }
                
                $availableServices = array_filter($data['services'], fn($s) => !in_array($s['name'], $p['credentials'] ?? []));
                $serviceOptions = '';
                foreach ($availableServices as $s) {
                    if ($s['configured']) {
                        $serviceOptions .= "<option value=\"{$s['name']}\">{$s['name']}</option>";
                    }
                }
                
                $ipRows = '';
                foreach ($p['ips'] ?? [] as $ip) {
                    $ipRows .= "<tr><td>{$ip}</td><td>
                        <form method=\"post\" action=\"/projects/{$p['id']}/ips/" . urlencode($ip) . "\" style=\"display:inline;\">
                            <input type=\"hidden\" name=\"_METHOD\" value=\"DELETE\">
                            <button type=\"submit\" style=\"background:none;border:none;color:#ef4444;cursor:pointer;padding:0;font:inherit;\">Remove</button>
                        </form>
                    </td></tr>";
                }
                
                $content = "<h2>{$p['name']}</h2>
                <p style=\"color:#94a3b8;margin-bottom:1.5rem;\">" . ($p['description'] ?? '') . "</p>
                <h3 style=\"color:#f8fafc;margin:1.5rem 0 1rem;\">Credentials</h3>
                <table><thead><tr><th>Service</th><th>Action</th></tr></thead><tbody>{$credRows}</tbody></table>
                <form method=\"post\" action=\"/projects/{$p['id']}/credentials\" style=\"margin-top:1rem;\">
                    <select name=\"service\">{$serviceOptions}</select>
                    <button type=\"submit\" class=\"btn\">Add Credential</button>
                </form>
                <h3 style=\"color:#f8fafc;margin:1.5rem 0 1rem;\">IP Whitelist</h3>
                <table><thead><tr><th>IP Address</th><th>Action</th></tr></thead><tbody>{$ipRows}</tbody></table>
                <form method=\"post\" action=\"/projects/{$p['id']}/ips\" style=\"margin-top:1rem;\">
                    <input type=\"text\" name=\"ip_address\" placeholder=\"e.g. 192.168.1.1\" required style=\"width:200px;\">
                    <button type=\"submit\" class=\"btn\">Add IP</button>
                </form>
                <div style=\"margin-top:2rem;\">
                    <a href=\"/projects/{$p['id']}/edit\" class=\"btn\">Edit</a>
                    <form method=\"post\" action=\"/projects/{$p['id']}\" style=\"display:inline;\">
                        <input type=\"hidden\" name=\"_METHOD\" value=\"DELETE\">
                        <button type=\"submit\" class=\"btn btn-danger\" onclick=\"return confirm('Delete project?')\">Delete</button>
                    </form>
                </div>";
                break;
        }
        
        echo $this->layout($content, $nav);
    }

    private function layout(string $content, string $nav): string
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects - Keymaster MCP</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; }
        .header { background: #1e293b; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #334155; }
        .header h1 { font-size: 1.25rem; color: #f8fafc; }
        .header a { color: #94a3b8; text-decoration: none; margin-left: 1.5rem; }
        .header a:hover { color: #f8fafc; }
        .container { padding: 2rem; max-width: 1200px; margin: 0 auto; }
        .section h2 { font-size: 1.125rem; margin-bottom: 1rem; color: #f8fafc; }
        .btn { padding: 0.5rem 1rem; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; font-size: 0.875rem; display: inline-block; }
        .btn:hover { background: #2563eb; }
        .btn-danger { background: #ef4444; }
        .btn-danger:hover { background: #dc2626; }
        table { width: 100%; border-collapse: collapse; background: #1e293b; border-radius: 8px; overflow: hidden; margin-bottom: 1rem; }
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #334155; }
        th { background: #334155; color: #f8fafc; font-weight: 500; }
        tr:hover { background: #273548; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; color: #94a3b8; margin-bottom: 0.5rem; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 0.75rem; border: 1px solid #334155; border-radius: 6px; background: #0f172a; color: #f8fafc; }
        select { padding: 0.5rem; border: 1px solid #334155; border-radius: 6px; background: #0f172a; color: #f8fafc; margin-right: 0.5rem; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Keymaster MCP</h1>
        <nav>' . $nav . '</nav>
    </div>
    <div class="container">
        <a href="/projects" style="color: #94a3b8; text-decoration: none; margin-bottom: 1rem; display: block;">Back to Projects</a>
        <div class="section">' . $content . '</div>
    </div>
</body>
</html>';
    }
}
