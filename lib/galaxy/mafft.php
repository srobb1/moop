<?php
/**
 * MAFFT (Multiple Sequence Alignment) Tool Wrapper for Galaxy
 */

require_once __DIR__ . '/galaxy_client.php';

class MAFFTTool {
    private $galaxy;
    private $config;
    
    /**
     * Initialize MAFFT tool
     * @param array $galaxyConfig Galaxy configuration from site_config
     */
    public function __construct($galaxyConfig) {
        $this->config = $galaxyConfig;
        $this->galaxy = new GalaxyClient(
            $galaxyConfig['url'],
            $galaxyConfig['api_key'],
            $galaxyConfig['mode'] ?? 'shared'
        );
    }
    
    /**
     * Run MAFFT alignment
     * @param int $userId User ID
     * @param array $sequences Sequence data [['id' => int, 'fasta' => string], ...]
     * @param array $options MAFFT options (algorithm, gap penalty, etc.)
     * @return array ['success' => bool, 'history_id' => string, 'job_id' => string, 'message' => string]
     */
    public function align($userId, $sequences, $options = []) {
        try {
            // Validate input
            if (empty($sequences)) {
                return [
                    'success' => false,
                    'message' => 'No sequences provided'
                ];
            }
            
            // Create history
            $historyId = $this->galaxy->createHistory($userId, 'MAFFT');
            
            // Create FASTA file from sequences
            $fastaContent = $this->sequencesToFasta($sequences);
            $fastaFile = $this->saveFastaFile($fastaContent);
            
            try {
                // Upload FASTA file
                $datasetId = $this->galaxy->uploadFile($historyId, $fastaFile, 'fasta');
                
                // Build MAFFT inputs
                $inputs = $this->buildMAFFTInputs($datasetId, $options);
                
                // Run MAFFT
                $jobInfo = $this->galaxy->runTool(
                    $historyId,
                    $this->config['tools']['mafft']['id'],
                    $inputs
                );
                
                return [
                    'success' => true,
                    'history_id' => $historyId,
                    'job_id' => $jobInfo['job_id'],
                    'output_id' => $jobInfo['output_id'],
                    'message' => 'MAFFT alignment started'
                ];
            } finally {
                @unlink($fastaFile);
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get alignment results
     * @param string $outputId Output dataset ID
     * @return array ['success' => bool, 'alignment' => string, 'message' => string]
     */
    public function getResults($outputId) {
        try {
            // Check dataset status
            $info = $this->galaxy->getDatasetInfo($outputId);
            
            if ($info['state'] !== 'ok') {
                return [
                    'success' => false,
                    'message' => 'Dataset not ready. State: ' . $info['state']
                ];
            }
            
            // Download alignment
            $alignment = $this->galaxy->getDatasetContent($outputId);
            
            return [
                'success' => true,
                'alignment' => $alignment,
                'file_size' => strlen($alignment),
                'message' => 'Alignment retrieved successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Monitor job progress
     * @param string $jobId Galaxy job ID
     * @return array Job status
     */
    public function getJobStatus($jobId) {
        try {
            $status = $this->galaxy->getJobStatus($jobId);
            
            return [
                'state' => $status['state'],
                'status' => $status['status'],
                'created' => $status['create_time'],
                'updated' => $status['update_time']
            ];
        } catch (Exception $e) {
            return [
                'state' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Wait for alignment to complete
     * @param string $jobId Galaxy job ID
     * @param int $timeout Seconds to wait
     * @return array Completion status
     */
    public function waitForCompletion($jobId, $timeout = 3600) {
        return $this->galaxy->waitForCompletion($jobId, $timeout);
    }
    
    // ============================================================
    // Helper methods
    // ============================================================
    
    /**
     * Convert sequences to FASTA format
     */
    private function sequencesToFasta($sequences) {
        $fasta = '';
        
        foreach ($sequences as $seq) {
            if (isset($seq['header']) && isset($seq['sequence'])) {
                // Already have header
                $fasta .= '>' . $seq['header'] . "\n";
                $fasta .= wordwrap($seq['sequence'], 80, "\n", true) . "\n";
            } else if (isset($seq['name']) && isset($seq['sequence'])) {
                // Have name, create header
                $fasta .= '>' . $seq['name'] . "\n";
                $fasta .= wordwrap($seq['sequence'], 80, "\n", true) . "\n";
            } else if (is_string($seq)) {
                // Already FASTA formatted
                $fasta .= $seq;
                if (substr($seq, -1) !== "\n") {
                    $fasta .= "\n";
                }
            }
        }
        
        return $fasta;
    }
    
    /**
     * Save FASTA content to temporary file
     */
    private function saveFastaFile($content) {
        $file = tempnam(sys_get_temp_dir(), 'mafft_');
        file_put_contents($file, $content);
        return $file;
    }
    
    /**
     * Build MAFFT tool inputs for Galaxy
     */
    private function buildMAFFTInputs($datasetId, $options) {
        // Default MAFFT options
        $defaults = [
            'method' => 'auto',        // auto, fft-ns-1, fft-ns-2, nwns, etc.
            'flavour' => 'nofft',      // nofft (default) or fft
            'maxiterate' => '0',       // 0 (default) or 1000
            'retree' => '2',           // 1 or 2 (default)
            'gap_open' => '1.53',      // Gap open penalty
            'gap_extend' => '0.0',     // Gap extension penalty
        ];
        
        // Merge with user options
        $params = array_merge($defaults, $options);
        
        // Build Galaxy tool inputs
        $inputs = [
            'input' => [
                'src' => 'hda',
                'id' => $datasetId
            ],
            'algorithm' => $params['method'],
            'flavour' => $params['flavour'],
            'op' => $params['gap_open'],
            'ep' => $params['gap_extend'],
            'maxiterate' => $params['maxiterate'],
            'retree' => $params['retree']
        ];
        
        return $inputs;
    }
}
?>
