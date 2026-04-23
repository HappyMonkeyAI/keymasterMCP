<?php

namespace App\Services;

class ApiService
{
    private string $baseUrl;
    private string $apiKey;
    private array $hmacHeaders = [];

    public function __construct(string $baseUrl, string $apiKey)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->generateHmacHeaders();
    }

    private function generateHmacHeaders(): void
    {
        $timestamp = time();
        $message = "GET:/api/services:{$timestamp}:";
        
        $secretHash = hash('sha256', $this->apiKey);
        $signature = hash_hmac('sha256', $message, $secretHash);

        $this->hmacHeaders = [
            'X-Client-Id' => 'php-frontend',
            'X-Timestamp' => (string)$timestamp,
            'X-Signature' => $signature,
        ];
    }

    private function signRequest(string $method, string $path, string $body = ''): array
    {
        $timestamp = time();
        $message = "{$method}:{$path}:{$timestamp}:{$body}";
        
        $secretHash = hash('sha256', $this->apiKey);
        $signature = hash_hmac('sha256', $message, $secretHash);

        return [
            'X-Client-Id' => 'php-frontend',
            'X-Timestamp' => (string)$timestamp,
            'X-Signature' => $signature,
        ];
    }

    private function request(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl . $endpoint;
        $body = $data ? json_encode($data) : '';
        
        $headers = $this->signRequest($method, $endpoint, $body);
        $headers['Content-Type'] = 'application/json';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(
            fn($k, $v) => "$k: $v",
            array_keys($headers),
            array_values($headers)
        ));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $httpCode,
            'data' => json_decode($response, true) ?? $response
        ];
    }

    public function get(string $endpoint): array
    {
        return $this->request('GET', $endpoint);
    }

    public function post(string $endpoint, array $data = []): array
    {
        return $this->request('POST', $endpoint, $data);
    }

    public function put(string $endpoint, array $data = []): array
    {
        return $this->request('PUT', $endpoint, $data);
    }

    public function delete(string $endpoint): array
    {
        return $this->request('DELETE', $endpoint);
    }

    public function getServices(): array
    {
        return $this->get('/api/services');
    }

    public function getCredentialGroups(): array
    {
        return $this->get('/api/credentials/groups');
    }

    public function createCredentialGroup(string $name, ?string $description = null): array
    {
        return $this->post('/api/credentials/groups', [
            'name' => $name,
            'description' => $description
        ]);
    }

    public function registerCredential(string $service, ?string $displayName = null, ?int $groupId = null, ?string $description = null): array
    {
        return $this->post('/api/credentials/register', [
            'service' => $service,
            'display_name' => $displayName,
            'group_id' => $groupId,
            'description' => $description
        ]);
    }

    public function getClients(): array
    {
        return $this->get('/api/clients');
    }

    public function createClient(string $clientId, ?string $name = null, ?string $email = null, string $role = 'developer'): array
    {
        return $this->post('/api/clients', [
            'client_id' => $clientId,
            'name' => $name,
            'email' => $email,
            'role' => $role
        ]);
    }

    public function getProjects(): array
    {
        return $this->get('/api/projects');
    }

    public function getProject(int $id): array
    {
        return $this->get("/api/projects/{$id}");
    }

    public function createProject(string $name, ?string $description = null, string $type = 'secrets'): array
    {
        return $this->post('/api/projects', [
            'name' => $name,
            'description' => $description,
            'type' => $type
        ]);
    }

    public function updateProject(int $id, string $name, ?string $description = null, string $type = 'secrets'): array
    {
        return $this->put("/api/projects/{$id}", [
            'name' => $name,
            'description' => $description,
            'type' => $type
        ]);
    }

    public function deleteProject(int $id): array
    {
        return $this->delete("/api/projects/{$id}");
    }

    public function addProjectCredential(int $projectId, string $service): array
    {
        return $this->post("/api/projects/{$projectId}/credentials", [
            'service' => $service
        ]);
    }

    public function removeProjectCredential(int $projectId, string $service): array
    {
        return $this->delete("/api/projects/{$projectId}/credentials/{$service}");
    }

    public function addProjectIp(int $projectId, string $ip): array
    {
        return $this->post("/api/projects/{$projectId}/ips", [
            'ip_address' => $ip
        ]);
    }

    public function removeProjectIp(int $projectId, string $ip): array
    {
        $encodedIp = urlencode($ip);
        return $this->delete("/api/projects/{$projectId}/ips/{$encodedIp}");
    }

    public function addKey(string $service, string $apiKey): array
    {
        return $this->post('/api/keys', [
            'service' => $service,
            'api_key' => $apiKey
        ]);
    }

    public function rotateKey(string $service, string $newApiKey): array
    {
        return $this->post('/api/keys/rotate', [
            'service' => $service,
            'new_api_key' => $newApiKey
        ]);
    }

    public function deleteKey(string $service): array
    {
        return $this->delete("/api/keys/{$service}");
    }

    public function getOrganization(): array
    {
        return $this->get('/api/organization');
    }

    public function updateOrganization(string $name, string $slug): array
    {
        return $this->put('/api/organization', [
            'name' => $name,
            'slug' => $slug
        ]);
    }

    public function getProjectSecrets(int $id): array
    {
        return $this->get("/api/projects/{$id}/secrets");
    }

    public function getProjectEnv(int $id): array
    {
        return $this->get("/api/projects/{$id}/env");
    }
}
