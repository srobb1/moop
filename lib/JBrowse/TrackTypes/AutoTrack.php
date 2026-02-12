<?php
/**
 * AUTO Track Type - Handles reference sequences and annotations
 * 
 * AUTO tracks resolve to:
 * - Reference sequence: data/genomes/{organism}/{assembly}/reference.fasta
 * - Annotations: data/genomes/{organism}/{assembly}/annotations.gff3.gz
 * 
 * These are configured at the assembly level, not as separate tracks.
 * 
 * @package MOOP\JBrowse
 * @subpackage TrackTypes
 */

require_once __DIR__ . '/TrackTypeInterface.php';

class AutoTrack implements TrackTypeInterface
{
    private $pathResolver;
    private $config;
    
    public function __construct($pathResolver)
    {
        $this->pathResolver = $pathResolver;
        $this->config = ConfigManager::getInstance();
    }
    
    /**
     * Get the track type identifier
     */
    public function getType(): string
    {
        return 'auto';
    }
    
    /**
     * Validate AUTO track data
     * 
     * AUTO tracks must specify category to determine type:
     * - "Genome Assembly" or "Sequence" → reference genome
     * - "Gene Models" or "Annotation" → annotations
     */
    public function validate($trackData)
    {
        $errors = [];
        
        // Check required fields
        foreach ($this->getRequiredFields() as $field) {
            if (empty($trackData[$field])) {
                $errors[] = "Missing required field: $field";
            }
        }
        
        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Determine AUTO type from category
        $category = strtolower($trackData['category']);
        $autoType = $this->determineAutoType($category);
        
        if (!$autoType) {
            $errors[] = "Cannot determine AUTO type from category: {$trackData['category']}. Expected 'Genome Assembly' or 'Gene Models'";
        }
        
        // Check if files exist
        $organism = $trackData['organism'];
        $assembly = $trackData['assembly'];
        
        if ($autoType === 'reference') {
            $fastaPath = $this->getReferencePath($organism, $assembly);
            if (!file_exists($fastaPath)) {
                $errors[] = "Reference FASTA not found: $fastaPath";
            }
            if (!file_exists($fastaPath . '.fai')) {
                $errors[] = "FASTA index not found: {$fastaPath}.fai (run: samtools faidx $fastaPath)";
            }
        } elseif ($autoType === 'annotation') {
            $gffPath = $this->getAnnotationPath($organism, $assembly);
            if (!file_exists($gffPath)) {
                $errors[] = "Annotation GFF not found: $gffPath";
            }
            if (!file_exists($gffPath . '.tbi')) {
                $errors[] = "GFF index not found: {$gffPath}.tbi (run: tabix -p gff $gffPath)";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Generate AUTO track
     * 
     * For reference sequences: Create/update assembly definition
     * For annotations: Create annotation track JSON
     */
    public function generate($trackData, $organism, $assembly, $options = [])
    {
        $category = strtolower($trackData['category']);
        $autoType = $this->determineAutoType($category);
        
        if (!$autoType) {
            error_log("Cannot determine AUTO type from category: {$trackData['category']}");
            return false;
        }
        
        try {
            if ($autoType === 'reference') {
                return $this->setupReferenceSequence($trackData, $organism, $assembly, $options);
            } elseif ($autoType === 'annotation') {
                return $this->setupAnnotation($trackData, $organism, $assembly, $options);
            }
        } catch (Exception $e) {
            error_log("AUTO track setup failed: " . $e->getMessage());
            return false;
        }
        
        return false;
    }
    
    /**
     * Setup reference sequence (assembly definition)
     */
    private function setupReferenceSequence($trackData, $organism, $assembly, $options)
    {
        $metadataDir = $this->config->getPath('metadata_path') . '/jbrowse2-configs/assemblies';
        $defFile = "$metadataDir/{$organism}_{$assembly}.json";
        
        // Check if already exists
        if (file_exists($defFile) && empty($options['force'])) {
            echo "  → Assembly definition already exists (use --force to regenerate)\n";
            return true;
        }
        
        if (!empty($options['dry_run'])) {
            echo "  [DRY RUN] Would create assembly definition: $defFile\n";
            return true;
        }
        
        // Create directory if needed
        if (!is_dir($metadataDir)) {
            mkdir($metadataDir, 0755, true);
        }
        
        // Get FASTA paths
        $fastaPath = $this->getReferencePath($organism, $assembly);
        $fastaUri = $this->pathResolver->toWebUri($fastaPath);
        
        // Get genome name from organism database
        $genomeName = $this->getGenomeName($organism);
        
        // Build assembly definition
        $definition = [
            'name' => "{$organism}_{$assembly}",
            'displayName' => $genomeName ?: "{$organism} ({$assembly})",
            'aliases' => [$assembly, $genomeName],
            'sequence' => [
                'type' => 'ReferenceSequenceTrack',
                'trackId' => "{$organism}_{$assembly}-ReferenceSequenceTrack",
                'adapter' => [
                    'type' => 'IndexedFastaAdapter',
                    'fastaLocation' => [
                        'uri' => $fastaUri,
                        'locationType' => 'UriLocation'
                    ],
                    'faiLocation' => [
                        'uri' => $fastaUri . '.fai',
                        'locationType' => 'UriLocation'
                    ]
                ]
            ],
            'metadata' => [
                'createdAt' => gmdate('Y-m-d\TH:i:s\Z'),
                'source' => 'PHP track generator',
                'description' => 'Assembly definition for ' . $organism . ' ' . $assembly
            ]
        ];
        
        // Write JSON
        $json = json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (file_put_contents($defFile, $json) === false) {
            throw new Exception("Failed to write assembly definition: $defFile");
        }
        
        echo "  ✓ Created assembly definition: $defFile\n";
        return true;
    }
    
    /**
     * Setup annotation track (GFF)
     */
    private function setupAnnotation($trackData, $organism, $assembly, $options)
    {
        $metadataDir = $this->config->getPath('metadata_path') . '/jbrowse2-configs/tracks';
        $trackDir = "$metadataDir/$organism/$assembly/gff";
        $trackFile = "$trackDir/{$trackData['track_id']}.json";
        
        // Check if already exists
        if (file_exists($trackFile) && empty($options['force'])) {
            echo "  → Annotation track already exists (use --force to regenerate)\n";
            return true;
        }
        
        if (!empty($options['dry_run'])) {
            echo "  [DRY RUN] Would create annotation track: $trackFile\n";
            return true;
        }
        
        // Create directory if needed
        if (!is_dir($trackDir)) {
            mkdir($trackDir, 0755, true);
        }
        
        // Get GFF paths
        $gffPath = $this->getAnnotationPath($organism, $assembly);
        $gffUri = $this->pathResolver->toWebUri($gffPath);
        
        // Build track metadata
        $metadata = [
            'trackId' => $trackData['track_id'],
            'name' => $trackData['name'],
            'assemblyNames' => ["{$organism}_{$assembly}"],
            'category' => [$trackData['category']],
            'type' => 'FeatureTrack',
            'adapter' => [
                'type' => 'Gff3TabixAdapter',
                'gffGzLocation' => [
                    'uri' => $gffUri,
                    'locationType' => 'UriLocation'
                ],
                'index' => [
                    'location' => [
                        'uri' => $gffUri . '.tbi',
                        'locationType' => 'UriLocation'
                    ]
                ]
            ],
            'displays' => [
                [
                    'type' => 'LinearBasicDisplay',
                    'displayId' => "{$trackData['track_id']}-LinearBasicDisplay"
                ]
            ],
            'metadata' => [
                'description' => $trackData['description'] ?? '',
                'access_level' => $trackData['access_level'] ?? 'PUBLIC',
                'file_path' => $gffPath,
                'is_remote' => false,
                'added_date' => gmdate('Y-m-d\TH:i:s\Z')
            ]
        ];
        
        // Add any extra metadata from sheet
        $extraFields = ['technique', 'institute', 'source', 'experiment', 'summary'];
        foreach ($extraFields as $field) {
            if (!empty($trackData[$field])) {
                $metadata['metadata']['google_sheets_metadata'][$field] = $trackData[$field];
            }
        }
        
        // Write JSON
        $json = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (file_put_contents($trackFile, $json) === false) {
            throw new Exception("Failed to write annotation track: $trackFile");
        }
        
        echo "  ✓ Created annotation track: $trackFile\n";
        return true;
    }
    
    /**
     * Determine AUTO type from category
     */
    private function determineAutoType($category)
    {
        $category = strtolower($category);
        
        // Reference genome indicators
        if (strpos($category, 'genome assembly') !== false || 
            strpos($category, 'sequence') !== false ||
            strpos($category, 'reference') !== false) {
            return 'reference';
        }
        
        // Annotation indicators
        if (strpos($category, 'gene model') !== false || 
            strpos($category, 'annotation') !== false ||
            strpos($category, 'genes') !== false) {
            return 'annotation';
        }
        
        return null;
    }
    
    /**
     * Get reference FASTA path
     * 
     * Uses ConfigManager to get genomes directory and file pattern
     */
    private function getReferencePath($organism, $assembly)
    {
        // Get genomes directory from config (portable!)
        $genomesDir = $this->config->get('jbrowse2')['genomes_directory'] ?? 
                      $this->config->getPath('site_path') . '/data/genomes';
        
        // Standard reference file name (could be made configurable)
        return "$genomesDir/$organism/$assembly/reference.fasta";
    }
    
    /**
     * Get annotation GFF path
     * 
     * Uses ConfigManager to get genomes directory
     */
    private function getAnnotationPath($organism, $assembly)
    {
        // Get genomes directory from config (portable!)
        $genomesDir = $this->config->get('jbrowse2')['genomes_directory'] ?? 
                      $this->config->getPath('site_path') . '/data/genomes';
        
        // Standard annotation file name (could be made configurable)
        return "$genomesDir/$organism/$assembly/annotations.gff3.gz";
    }
    
    /**
     * Get genome display name from organism database
     * 
     * Uses ConfigManager to get organisms directory
     */
    private function getGenomeName($organism)
    {
        // Get organisms directory from config (portable!)
        $organismsDir = $this->config->getPath('organism_data');
        $dbPath = "$organismsDir/$organism/organism.sqlite";
        
        if (!file_exists($dbPath)) {
            return null;
        }
        
        try {
            $db = new SQLite3($dbPath);
            $result = $db->querySingle("SELECT genome_name FROM genome LIMIT 1");
            $db->close();
            return $result;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Get required fields
     */
    public function getRequiredFields()
    {
        return ['track_id', 'name', 'category', 'TRACK_PATH'];
    }
    
    /**
     * Get valid extensions (AUTO uses keyword, not extension)
     */
    public function getValidExtensions()
    {
        return ['AUTO'];
    }
    
    /**
     * Check if index is required
     */
    public function requiresIndex()
    {
        return false; // AUTO handles indexes internally
    }
    
    /**
     * Get index extensions
     */
    public function getIndexExtensions()
    {
        return [];
    }
}
