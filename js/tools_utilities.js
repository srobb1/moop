/**
 * Tools Utilities - Shared JavaScript functions for tools
 * Used by BLAST and other sequence analysis tools
 */

/**
 * Detect if a sequence is protein or nucleotide
 * 
 * @param {string} sequence - The input sequence (FASTA or raw)
 * @returns {Object} Detection result with type and message
 *   Returns: {type: 'protein|nucleotide|unknown', message: 'Human readable message'}
 */
function detectSequenceType(sequence) {
    sequence = sequence.trim();
    
    if (!sequence) {
        return { type: 'unknown', message: '' };
    }
    
    // Extract just the sequence part (remove FASTA header if present)
    let seq = sequence;
    if (seq.startsWith('>')) {
        const lines = seq.split('\n');
        seq = lines.slice(1).join('').toUpperCase();
    } else {
        seq = seq.toUpperCase();
    }
    
    // Remove whitespace
    seq = seq.replace(/\s/g, '');
    
    // Determine sequence type
    // Count characters to determine type
    const proteinChars = (seq.match(/[EFIPQZ]/g) || []).length;  // Unique to proteins
    const nucleotideChars = (seq.match(/[U]/g) || []).length;    // U is unique to RNA
    const ambiguousDNA = (seq.match(/[ATGCN]/g) || []).length;   // DNA bases + N
    const allProteinAA = (seq.match(/[ACDEFGHIKLMNPQRSTVWY]/g) || []).length;  // All protein AAs
    
    let detectedType = 'unknown';
    let message = '';
    
    // If has U (RNA), it's nucleotide
    if (nucleotideChars > 0) {
        detectedType = 'nucleotide';
        message = '✓ DNA/RNA sequence detected - BLASTn, BLASTx, and tBLASTx programs available';
    } 
    // If has protein-unique chars, it's protein
    else if (proteinChars > 0) {
        detectedType = 'protein';
        message = '✓ Protein sequence detected - BLASTp and tBLASTn programs available';
    }
    // If mostly DNA bases with no protein chars
    else if (ambiguousDNA > allProteinAA * 0.8) {
        detectedType = 'nucleotide';
        message = '✓ DNA/RNA sequence detected - BLASTn, BLASTx, and tBLASTx programs available';
    }
    // Check ratio of protein-likely chars vs DNA-likely chars
    else {
        // Count how many chars are only in protein (D, H, W, Y are very rare in DNA)
        const proteinLikelyChars = (seq.match(/[DWY]/g) || []).length;  // Less common in DNA
        const nucleotideLikelyChars = (seq.match(/[ATGC]/g) || []).length;
        
        // If more protein-likely chars than DNA-likely chars, it's protein
        if (proteinLikelyChars > nucleotideLikelyChars * 0.5) {
            detectedType = 'protein';
            message = '✓ Protein sequence detected - BLASTp and tBLASTn programs available';
        } else {
            detectedType = 'unknown';
            message = '? Sequence type unclear - All programs available';
        }
    }
    
    return { type: detectedType, message: message };
}

/**
 * Filter BLAST program options based on sequence type
 * 
 * @param {string} sequenceType - The detected sequence type ('protein', 'nucleotide', or 'unknown')
 * @param {string} programSelectId - The ID of the select element
 */
function filterBlastPrograms(sequenceType, programSelectId) {
    const programSelect = document.getElementById(programSelectId);
    if (!programSelect) {
        return;
    }
    
    // BLAST program requirements:
    // blastn (DNA→DNA): needs nucleotide query
    // blastp (Protein→Protein): needs protein query
    // blastx (DNA→Protein): needs nucleotide query
    // tblastn (Protein→DNA): needs protein query
    // tblastx (DNA→DNA): needs nucleotide query
    
    const proteinPrograms = ['blastp', 'tblastn'];        // Protein input programs
    const nucleotidePrograms = ['blastn', 'blastx', 'tblastx'];  // Nucleotide input programs
    
    // Disable/enable options based on sequence type
    document.querySelectorAll('#' + programSelectId + ' option').forEach(opt => {
        if (sequenceType === 'protein') {
            opt.disabled = !proteinPrograms.includes(opt.value);
        } else if (sequenceType === 'nucleotide') {
            opt.disabled = !nucleotidePrograms.includes(opt.value);
        } else {
            opt.disabled = false;
        }
    });
    
    // If current selection is disabled, select first available
    if (programSelect.options[programSelect.selectedIndex].disabled) {
        for (let i = 0; i < programSelect.options.length; i++) {
            if (!programSelect.options[i].disabled) {
                programSelect.selectedIndex = i;
                break;
            }
        }
    }
}

/**
 * Update the sequence type info display
 * Shows a message about the detected sequence type and available programs
 * 
 * @param {string} message - The message to display
 * @param {string} containerElementId - The ID of the container div
 * @param {string} messageElementId - The ID of the element within the container for the message text
 */
function updateSequenceTypeInfo(message, containerElementId, messageElementId) {
    const container = document.getElementById(containerElementId);
    const messageEl = document.getElementById(messageElementId);
    
    if (!container || !messageEl) {
        return;
    }
    
    if (message) {
        messageEl.textContent = message;
        container.style.display = 'block';
    } else {
        container.style.display = 'none';
    }
}

/**
 * Update the list of available databases based on selected assembly and BLAST program
 * Called when assembly selection changes or BLAST program changes
 * 
 * @param {Object} databasesByAssembly - Global object with databases organized by assembly
 */
function updateDatabaseList() {
    // Safety check - databasesByAssembly needs to be defined
    if (typeof databasesByAssembly === 'undefined') {
        console.error('databasesByAssembly not loaded yet');
        return;
    }
    
    const selectedRadio = document.querySelector('input[name="selected_source"]:checked');
    const program = document.getElementById('blast_program').value;
    const dbBadges = document.getElementById('databaseBadges');
    
    if (!dbBadges) {
        console.error('databaseBadges element not found');
        return;
    }
    
    if (!selectedRadio) {
        dbBadges.innerHTML = '<div style="padding: 15px; text-align: center; color: #666; width: 100%;"><small>Select an assembly first</small></div>';
        return;
    }
    
    const sourceKey = selectedRadio.value;
    const allDbs = databasesByAssembly[sourceKey] || [];
    
    // Filter by program compatibility
    // blastp (Protein→Protein) and blastx (DNA→Protein) need Protein DB
    // blastn, tblastn, tblastx need Nucleotide DB
    const compatibleDbs = allDbs.filter(db => {
        if (['blastp', 'blastx'].includes(program)) return db.type === 'protein';
        if (['blastn', 'tblastn', 'tblastx'].includes(program)) return db.type === 'nucleotide';
        return true;
    });
    
    if (compatibleDbs.length === 0) {
        dbBadges.innerHTML = '<div style="padding: 15px; text-align: center; color: #d9534f; width: 100%;"><small>No compatible databases found for this program</small></div>';
        return;
    }
    
    let html = '';
    compatibleDbs.forEach((db, index) => {
        const typeLabel = db.type === 'nucleotide' ? 'DNA' : 'Protein';
        const badgeClass = db.type === 'nucleotide' ? 'bg-primary' : 'bg-success';
        const isChecked = index === 0 ? 'checked' : '';
        const dbId = 'db_' + db.path.replace(/[^\w]/g, '_');
        
        html += `
            <div class="fasta-source-line">
                <input 
                    type="radio" 
                    name="blast_db" 
                    value="${db.path}"
                    ${isChecked}
                    id="${dbId}"
                >
                <span class="badge badge-sm ${badgeClass} text-white">${typeLabel}</span>
                <label for="${dbId}" style="margin: 0; cursor: pointer; flex: 1;">
                    <strong>${db.name}</strong>
                </label>
            </div>
        `;
    });
    
    dbBadges.innerHTML = html;
    
    // Update hidden form fields
    const form = document.getElementById('blastForm');
    if (form && selectedRadio) {
        form.querySelector('input[name="organism"]').value = selectedRadio.dataset.organism;
        form.querySelector('input[name="assembly"]').value = selectedRadio.dataset.assembly;
    }
}

