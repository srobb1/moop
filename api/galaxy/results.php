<?php
/**
 * Galaxy Analysis Results API
 * GET /api/galaxy/results.php?job_id=XXX&output_id=YYY&action=status|results|wait
 * 
 * Polls for job completion and retrieves results
 */

header('Content-Type: application/json');

try {
    // Load configuration and classes
    $config = require __DIR__ . '/../../config/site_config.php';
    require_once __DIR__ . '/../../includes/user.php';
    require_once __DIR__ . '/../../lib/galaxy/index.php';
    
    // Check if Galaxy is enabled
    if (empty($config['galaxy_settings']['enabled'])) {
        throw new Exception('Galaxy integration is not enabled');
    }
    
    // Check API key
    if (empty($config['galaxy_settings']['api_key'])) {
        throw new Exception('Galaxy API key not configured');
    }
    
    // Get current user
    $user = new User();
    if (!$user->getId()) {
        throw new Exception('User not authenticated');
    }
    
    // Get parameters
    $action = $_GET['action'] ?? 'status';
    $jobId = $_GET['job_id'] ?? null;
    $outputId = $_GET['output_id'] ?? null;
    
    // Initialize Galaxy client
    $galaxy = new GalaxyClient(
        $config['galaxy_settings']['url'],
        $config['galaxy_settings']['api_key']
    );
    
    // Handle different actions
    if ($action === 'status') {
        // Get job status
        if (!$jobId) {
            throw new Exception('job_id parameter required');
        }
        
        $status = $galaxy->getJobStatus($jobId);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'status' => $status
        ]);
        
    } else if ($action === 'results') {
        // Get completed results
        if (!$outputId) {
            throw new Exception('output_id parameter required');
        }
        
        $mafft = new MAFFTTool($config['galaxy_settings']);
        $result = $mafft->getResults($outputId);
        
        http_response_code($result['success'] ? 200 : 400);
        echo json_encode($result);
        
    } else if ($action === 'wait') {
        // Wait for completion and return results
        if (!$jobId || !$outputId) {
            throw new Exception('job_id and output_id parameters required');
        }
        
        $timeout = isset($_GET['timeout']) ? (int)$_GET['timeout'] : 3600;
        
        // Wait for job
        $completion = $galaxy->waitForCompletion($jobId, $timeout);
        
        if (!$completion['success']) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $completion['error'],
                'status' => $completion['status']
            ]);
        } else {
            // Get results
            $mafft = new MAFFTTool($config['galaxy_settings']);
            $result = $mafft->getResults($outputId);
            
            http_response_code($result['success'] ? 200 : 400);
            echo json_encode($result);
        }
        
    } else {
        throw new Exception('Unknown action: ' . $action);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
