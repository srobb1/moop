<?php

/**
 * PIF Track Type Handler (Pairwise Indexed PAF)
 * 
 * Handles PIF.GZ format tracks for whole genome synteny visualization
 * Shows syntenic relationships between two assemblies using minimap2-derived data
 */

require_once __DIR__ . '/TrackTypeInterface.php';
require_once __DIR__ . '/../PathResolver.php';

class PIFTrack implements TrackTypeInterface
{
    private $pathResolver;
    private $config;
    
    public function __construct(PathResolver $pathResolver, $config)
    {
        $this->pathResolver = $pathResolver;
        $this->config = $config;
    }
    
    public function getType()
    {
        return 'pif';
    }
    
    public function getValidExtensions()
    {
        return ['.pif.gz'];
    }
    
    public function requiresIndex()
    {
        return true;
    }
    
    public function getIndexExtensions()
    {
        return ['.tbi'];
    }
    
    public function getRequiredFields()
    {
        return ['track_id', 'name', 'track_path', 'assembly1', 'assembly2'];
    }
    
    public function validate($trackData)
    {
        $errors = [];
        
        foreach ($this->getRequiredFields() as $field) {
            if (!isset($trackData[$field]) || empty($trackData[$field])) {
                $errors[] = "Missing required field: $field";
            }
        }
        
        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors];
        }
        
        $path = $trackData['track_path'];
        if (!preg_match('/\.pif\.gz$/i', $path)) {
            $errors[] = "Invalid file extension. Expected: .pif.gz";
        }
        
        if (!preg_match('/^https?:\/\//i', $path)) {
            if (!file_exists($path)) {
                $errors[] = "File not found: $path";
            }
            
            $tbiPath = $path . '.tbi';
            if (!file_exists($tbiPath)) {
                $errors[] = "TBI index not found: $tbiPath";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    public function generate($trackData, $organism, $assembly, $options = [])
    {
        try {
            $assembly1 = $trackData['assembly1'];
            $assembly2 = $assembly;
            
            $metadata = $this->buildMetadata($trackData['track_path'], array_merge([
                'organism' => $organism,
                'assembly1' => $assembly1,
                'assembly2' => $assembly2,
            ], $trackData, $options));
            
            if (empty($options['dry_run'])) {
                $this->writeMetadata($organism, $assembly2, $metadata);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("PIF track generation failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function buildMetadata(string $filePath, array $options): array
    {
        $organism = $options['organism'];
        $assembly1 = $options['assembly1'];
        $assembly2 = $options['assembly2'];
        $trackId = $options['track_id'] ?? basename($filePath, '.pif.gz');
        $trackName = $options['name'] ?? str_replace(['_', '-'], ' ', basename($filePath, '.pif.gz'));
        $category = $options['category'] ?? 'Synteny';
        $accessLevel = isset($options['access_level']) && !empty($options['access_level'])
            ? $options['access_level']
            : 'Public';
        
        $isRemote = preg_match('/^https?:\/\//i', $filePath);
        
        if ($isRemote) {
            $pifUri = $filePath;
            $tbiUri = $filePath . '.tbi';
        } else {
            $pifUri = $this->pathResolver->toWebUri($filePath);
            $tbiUri = $this->pathResolver->toWebUri($filePath . '.tbi');
        }
        
        $metadata = [
            'trackId' => $trackId,
            'name' => $trackName,
            'organism' => $organism,
            'assembly1' => $assembly1,
            'assembly2' => $assembly2,
            'category' => [$category],
            'config' => [
                'type' => 'SyntenyTrack',
                'trackId' => $trackId,
                'name' => $trackName,
                'category' => [$category],
                'assemblyNames' => [$assembly2, $assembly1],
                'adapter' => [
                    'type' => 'PairwiseIndexedPAFAdapter',
                    'pifGzLocation' => [
                        'uri' => $pifUri,
                        'locationType' => 'UriLocation'
                    ],
                    'index' => [
                        'location' => [
                            'uri' => $tbiUri,
                            'locationType' => 'UriLocation'
                        ],
                        'indexType' => 'TBI'
                    ]
                ],
                'metadata' => [
                    'access_level' => $accessLevel,
                ]
            ]
        ];
        
        return $metadata;
    }
    
    private function writeMetadata(string $organism, string $assembly, array $metadata): void
    {
        $metadataDir = $this->config->getPath('metadata_path') . '/jbrowse2-configs/tracks';
        $trackDir = "$metadataDir/$organism/$assembly/pif";
        
        if (!is_dir($trackDir)) {
            mkdir($trackDir, 0775, true);
        }
        
        $outputFile = "$trackDir/{$metadata['trackId']}.json";
        
        file_put_contents(
            $outputFile,
            json_encode($metadata['config'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        
        chmod($outputFile, 0664);
    }
}
