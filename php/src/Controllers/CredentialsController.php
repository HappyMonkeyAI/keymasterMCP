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
        $result = $this->api->getServices();
        $services = $result['status'] === 200 ? $result['data'] : [];

        $rows = '';
        foreach ($services as $s) {
            $status = $s['configured'] 
                ? '<span style="color: #22c55e;">● Configured</span>' 
                : '<span style="color: #ef4444;">○ Not configured</span>';
            
            if ($s['configured']) {
                $action = "<a href=\"/credentials/{$s['name']}/edit\" class=\"btn\">Update</a>
                           <form method=\"post\" action=\"/credentials/{$s['name']}\" style=\"display:inline;\">
                               <input type=\"hidden\" name=\"_METHOD\" value=\"DELETE\">
                               <button type=\"submit\" class=\"btn btn-danger\" onclick=\"return confirm('Delete this key?')\">Delete</button>
                           </form>";
            } else {
                $action = "<a href=\"/credentials/new?service={$s['name']}\" class=\"btn\">Add Key</a>";
            }
            
            $rows .= "<tr><td>{$s['name']}</td><td>{$status}</td><td>{$action}</td></tr>";
        }

        $this->render("
        <div class=\"section\">
            <h2>API Credentials</h2>
            <table>
                <thead><tr><th>Service</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>{$rows}</tbody>
            </table>
        </div>");
    }

    public function new()
    {
        $service = $_GET['service'] ?? 'openai';
        
        $services = ['openai', 'anthropic', 'github'];
        $options = '';
        foreach ($services as $s) {
            $selected = $s === $service ? 'selected' : '';
            $options .= "<option value=\"{$s}\" {$selected}>{$s}</option>";
        }

        $this->render("
        <div class=\"section\">
            <h2>Add API Key</h2>
            <form method=\"post\" action=\"/credentials\" style=\"max-width:500px;\">
                <div class=\"form-group\">
                    <label>Service</label>
                    <select name=\"service\">{$options}</select>
                </div>
                <div class=\"form-group\">
                    <label>API Key</label>
                    <input type=\"text\" name=\"api_key\" placeholder=\"sk-...\" required>
                </div>
                <button type=\"submit\" class=\"btn\">Add</button>
                <a href=\"/credentials\" class=\"btn\" style=\"background:#64748b;\">Cancel</a>
            </form>
        </div>");
    }

    public function create()
    {
        $data = $_POST;
        $result = $this->api->addKey($data['service'], $data['api_key']);
        
        if ($result['status'] === 201) {
            header('Location: /credentials');
            exit;
        }
        
        echo "Error adding key: " . ($result['data']['detail'] ?? 'Unknown error');
    }

    public function edit($service)
    {
        $this->render("
        <div class=\"section\">
            <h2>Update API Key - {$service}</h2>
            <form method=\"post\" action=\"/credentials/{$service}\" style=\"max-width:500px;\">
                <input type=\"hidden\" name=\"_METHOD\" value=\"PUT\">
                <div class=\"form-group\">
                    <label>New API Key</label>
                    <input type=\"text\" name=\"api_key\" placeholder=\"sk-...\" required>
                </div>
                <button type=\"submit\" class=\"btn\">Update</button>
                <a href=\"/credentials\" class=\"btn\" style=\"background:#64748b;\">Cancel</a>
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
        $result = $this->api->deleteKey($service);
        header('Location: /credentials');
        exit;
    }

    private function render($content) {
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credentials - Keymaster MCP</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; }
        .header { background: #1e293b; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #334155; }
        .header h1 { font-size: 1.25rem; color: #f8fafc; }
        .header a { color: #94a3b8; text-decoration: none; margin-left: 1.5rem; }
        .header a:hover { color: #f8fafc; }
        .container { padding: 2rem; max-width: 1000px; margin: 0 auto; }
        .section h2 { font-size: 1.125rem; margin-bottom: 1rem; color: #f8fafc; }
        .btn { padding: 0.5rem 1rem; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; font-size: 0.875rem; display: inline-block; margin-right: 0.5rem; }
        .btn:hover { background: #2563eb; }
        .btn-danger { background: #ef4444; }
        .btn-danger:hover { background: #dc2626; }
        table { width: 100%; border-collapse: collapse; background: #1e293b; border-radius: 8px; overflow: hidden; margin-bottom: 1rem; }
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #334155; }
        th { background: #334155; color: #f8fafc; font-weight: 500; }
        tr:hover { background: #273548; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; color: #94a3b8; margin-bottom: 0.5rem; }
        .form-group input, .form-group select { width: 100%; padding: 0.75rem; border: 1px solid #334155; border-radius: 6px; background: #0f172a; color: #f8fafc; }
        select { padding: 0.5rem; border: 1px solid #334155; border-radius: 6px; background: #0f172a; color: #f8fafc; }
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
    <div class="container">
        <a href="/credentials" style="color: #94a3b8; text-decoration: none; margin-bottom: 1rem; display: block;">← Back to Credentials</a>
        <div class="section">' . $content . '</div>
    </div>
</body>
</html>';
    }
}
