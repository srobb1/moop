<?php
/**
 * Galaxy API Client for MOOP
 * Handles communication with UseGalaxy.org
 */

class GalaxyClient {
    private $url;
    private $apiKey;
    private $mode;
    private $timeout = 30;
    
    /**
     * Initialize Galaxy client
     * @param string $url Galaxy server URL (e.g., https://usegalaxy.org)
     * @param string $apiKey API key for authentication
     * @param string $mode 'shared' or 'per_user' (for future enhancement)
     */
    public function __construct($url, $apiKey, $mode = 'shared') {
        $this->url = rtrim($url, '/');
        $this->apiKey = $apiKey;
        $this->mode = $mode;
    }
    
    /**
     * Test API connection
     * @return array ['success' => bool, 'email' => string, 'message' => string]
     */
    public function testConnection() {
        try {
            $response = $this->get('/api/users/current');
            
            if ($response && isset($response['quota_percent'])) {
                return [
                    'success' => true,
                    'message' => 'Connected to Galaxy'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Invalid response from Galaxy'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create a new history for analysis
     * @param int $userId MOOP user ID
     * @param string $toolName Name of the tool being used
     * @return string History ID
     */
    public function createHistory($userId, $toolName) {
        $historyName = sprintf(
            "MOOP - User %d - %s - %s",
            $userId,
            $toolName,
            date('Y-m-d H:i:s')
        );
        
        $response = $this->post('/api/histories', [
            'name' => $historyName
        ]);
        
        if (!$response || !isset($response['id'])) {
            throw new Exception('Failed to create history: ' . json_encode($response));
        }
        
        return $response['id'];
    }
    
    /**
     * Upload sequences to a history
     * @param string $historyId History ID
     * @param string $filename Local filename to upload
     * @param string $fileType File type (fasta, fastq, etc.)
     * @return string Dataset ID
     */
    public function uploadFile($historyId, $filename, $fileType = 'fasta') {
        if (!file_exists($filename)) {
            throw new Exception("File not found: $filename");
        }
        
        // Read file content
        $fileContent = file_get_contents($filename);
        
        // For UseGalaxy.org, we need to use form data
        // Create a temporary URL or use direct upload
        $response = $this->uploadFileContent(
            $historyId,
            basename($filename),
            $fileContent,
            $fileType
        );
        
        if (!$response || !isset($response['id'])) {
            throw new Exception('Failed to upload file: ' . json_encode($response));
        }
        
        return $response['id'];
    }
    
    /**
     * Upload file content directly
     * @param string $historyId History ID
     * @param string $filename Filename
     * @param string $content File content
     * @param string $fileType File type
     * @return array Response with dataset ID
     */
    private function uploadFileContent($historyId, $filename, $content, $fileType) {
        // Write to temporary file
        $tmpFile = tempnam(sys_get_temp_dir(), 'galaxy_');
        file_put_contents($tmpFile, $content);
        
        try {
            // Post with file upload
            $ch = curl_init($this->url . '/api/histories/' . urlencode($historyId) . '/contents');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            
            $postData = [
                'files_0|file_data' => new CURLFile($tmpFile),
                'files_0|NAME' => $filename,
                'file_type' => $fileType,
                'api_key' => $this->apiKey
            ];
            
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            @unlink($tmpFile);
            
            if ($httpCode >= 200 && $httpCode < 300) {
                $data = json_decode($response, true);
                // Response might be array or single object
                if (is_array($data) && count($data) > 0) {
                    return $data[0];
                } else if (isset($data['id'])) {
                    return $data;
                }
            }
            
            return null;
        } catch (Exception $e) {
            @unlink($tmpFile);
            throw $e;
        }
    }
    
    /**
     * Run a tool
     * @param string $historyId History ID
     * @param string $toolId Tool ID (from Galaxy)
     * @param array $inputs Tool inputs (dataset IDs, parameters, etc.)
     * @return string Job ID
     */
    public function runTool($historyId, $toolId, $inputs) {
        $payload = [
            'history_id' => $historyId,
            'tool_id' => $toolId,
            'inputs' => $inputs
        ];
        
        $response = $this->post('/api/tools', $payload);
        
        if (!$response || !isset($response['outputs'][0]['id'])) {
            throw new Exception('Failed to run tool: ' . json_encode($response));
        }
        
        // Return the output dataset ID (we'll use this to track the job)
        return [
            'job_id' => $response['jobs'][0]['id'] ?? null,
            'output_id' => $response['outputs'][0]['id']
        ];
    }
    
    /**
     * Get job status
     * @param string $jobId Job ID
     * @return array Job status info
     */
    public function getJobStatus($jobId) {
        $response = $this->get('/api/jobs/' . urlencode($jobId));
        
        return [
            'state' => $response['state'] ?? 'unknown',
            'status' => $response['status'] ?? 'unknown',
            'create_time' => $response['create_time'] ?? null,
            'update_time' => $response['update_time'] ?? null,
            'tool_id' => $response['tool_id'] ?? null
        ];
    }
    
    /**
     * Get dataset contents
     * @param string $datasetId Dataset ID
     * @return string Dataset content
     */
    public function getDatasetContent($datasetId) {
        // Download dataset
        $url = $this->url . '/api/datasets/' . urlencode($datasetId) . '/download?api_key=' . urlencode($this->apiKey);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return $content;
        } else {
            throw new Exception("Failed to download dataset: HTTP $httpCode");
        }
    }
    
    /**
     * Poll for job completion
     * @param string $jobId Job ID
     * @param int $maxWaitSeconds Maximum seconds to wait
     * @return array Final job status
     */
    public function waitForCompletion($jobId, $maxWaitSeconds = 3600) {
        $startTime = time();
        $pollInterval = 5; // Start with 5 seconds
        
        while ((time() - $startTime) < $maxWaitSeconds) {
            $status = $this->getJobStatus($jobId);
            
            if ($status['state'] === 'ok') {
                return ['success' => true, 'status' => $status];
            } else if ($status['state'] === 'error') {
                return ['success' => false, 'status' => $status, 'error' => 'Job failed'];
            }
            
            // Wait before polling again
            sleep($pollInterval);
            // Gradually increase poll interval (up to 30 seconds)
            $pollInterval = min($pollInterval + 2, 30);
        }
        
        return [
            'success' => false,
            'status' => $status,
            'error' => 'Job timeout'
        ];
    }
    
    /**
     * Get dataset info
     * @param string $datasetId Dataset ID
     * @return array Dataset information
     */
    public function getDatasetInfo($datasetId) {
        $response = $this->get('/api/datasets/' . urlencode($datasetId));
        
        return [
            'id' => $response['id'] ?? null,
            'name' => $response['name'] ?? null,
            'state' => $response['state'] ?? null,
            'visible' => $response['visible'] ?? true,
            'file_size' => $response['file_size'] ?? 0,
            'created' => $response['create_time'] ?? null
        ];
    }
    
    /**
     * List histories
     * @return array List of histories
     */
    public function listHistories($limit = 10) {
        $response = $this->get('/api/histories?limit=' . $limit);
        
        if (!is_array($response)) {
            return [];
        }
        
        return $response;
    }
    
    /**
     * Delete history (cleanup)
     * @param string $historyId History ID
     * @return bool Success
     */
    public function deleteHistory($historyId) {
        try {
            $response = $this->delete('/api/histories/' . urlencode($historyId));
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    // ============================================================
    // Private helper methods
    // ============================================================
    
    /**
     * Make GET request to Galaxy API
     */
    private function get($endpoint) {
        $url = $this->url . $endpoint;
        
        // Add API key as query parameter
        $separator = strpos($url, '?') === false ? '?' : '&';
        $url .= $separator . 'api_key=' . urlencode($this->apiKey);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL error: $error");
        }
        
        if ($httpCode >= 400) {
            throw new Exception("Galaxy API error ($httpCode): $response");
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Make POST request to Galaxy API
     */
    private function post($endpoint, $data) {
        $url = $this->url . $endpoint . '?api_key=' . urlencode($this->apiKey);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL error: $error");
        }
        
        if ($httpCode >= 400) {
            throw new Exception("Galaxy API error ($httpCode): $response");
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Make DELETE request to Galaxy API
     */
    private function delete($endpoint) {
        $url = $this->url . $endpoint . '?api_key=' . urlencode($this->apiKey);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 400) {
            throw new Exception("Galaxy API error ($httpCode): $response");
        }
        
        return json_decode($response, true);
    }
}
?>
