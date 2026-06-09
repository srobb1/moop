<?php
/**
 * TrackTypeInterface - Interface for all JBrowse track types
 * 
 * Defines the contract that all track type implementations must follow.
 * Each track type (BigWig, BAM, VCF, GFF, etc.) implements this interface.
 * 
 * The Strategy Pattern allows:
 * - Adding new track types without modifying existing code
 * - Independent validation rules per track type
 * - Clear separation of track-specific logic
 * 
 * @package MOOP\JBrowse\TrackTypes
 */

interface TrackTypeInterface
{
    /**
     * Validate track data specific to this track type
     * 
     * Checks:
     * - Required fields present
     * - File extension matches track type
     * - File exists (if local)
     * - Index files exist (for indexed types like BAM, VCF)
     * 
     * @param array $trackData Track data from Google Sheet
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validate($trackData);
    
    /**
     * Generate track by calling appropriate bash script
     * 
     * Steps:
     * 1. Resolve track path using PathResolver
     * 2. Build command for bash script (add_bigwig_track.sh, etc.)
     * 3. Execute command
     * 4. Return success/failure
     * 
     * @param array $trackData Track data from Google Sheet
     * @param string $organism Organism name
     * @param string $assembly Assembly ID
     * @param array $options Optional: force, dry_run, etc.
     * @return bool True if track created successfully
     */
    public function generate($trackData, $organism, $assembly, $options = []);
    
    /**
     * Get required fields for this track type
     * 
     * Used for validation and error messages.
     * 
     * @return array List of required field names
     */
    public function getRequiredFields();
    
    /**
     * Get track type identifier
     * 
     * Returns: 'bigwig', 'bam', 'vcf', 'gff', etc.
     * 
     * @return string Track type identifier
     */
    public function getType();
    
    /**
     * Get valid file extensions for this track type
     * 
     * Used for validation.
     * 
     * @return array List of valid extensions (e.g., ['.bw', '.bigwig'])
     */
    public function getValidExtensions();
    
    /**
     * Check if this track type requires index files
     * 
     * @return bool True if index files required (BAM, VCF, CRAM)
     */
    public function requiresIndex();
    
    /**
     * Get expected index file extension(s)
     * 
     * Returns empty array if no index required.
     * 
     * @return array Index extensions (e.g., ['.bai'] for BAM, ['.tbi'] for VCF)
     */
    public function getIndexExtensions();
}
