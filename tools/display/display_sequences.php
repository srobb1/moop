<?php

        if ( file_exists("$json_files_path/customization/organisms.json") ) {
            $sps_json_file = file_get_contents("$json_files_path/customization/organisms.json");

            // var_dump($sps_json_file);

            $species_hash = json_decode($sps_json_file, true);
            //var_dump($species_hash);

	}

?>     


<div class="card">
  <div class="card-header">
    <div class="collapse_section pointer_cursor" data-toggle="collapse" data-target="#Seqs" aria-expanded="true">
      <i class="fas fa-minus toggle-icon" style="color:#229dff"></i> <span class="alert alert-info">Sequences</span>
    </div>
  </div>
  <div id="Seqs" class="collapse show">
    <div class="card-body" style="padding-left: 20px;">
      <div id="Seqs_wrapper" class="dataTables_wrapper dt-bootstrap4 no-footer">
        <!-- Existing content -->

<?php
# $species_name from gene.php script
$count = 0;
$db_types = ['protein_fasta','transcript_fasta','cds_fasta'];
foreach ($db_types as $db_type) { 
  $parts = [];
  $count++;
  foreach ([trim($genus),trim($species)] as $each){
    if (!empty($each)){ 
      $parts[] = $each;
    }
  }
  $species_name= trim(implode(' ', $parts));
  $blast_db =  $species_hash[$species_name][$db_type]; 	
  $bdb_path = $blast_dbs_path;
  $full_path_db = $bdb_path.'/'.$blast_db;
  exec("blastdbcmd -db {$full_path_db} -entry " . escapeshellarg($gene_name) ."| sed 's/lcl|//'" ,$ret);
  if ($ret){
	  echo '<div class="card">';
	  echo '  <div class="card-header">';
	  echo '<div class="colloapse_section pointer_cursor" data-toggle="collapse" data-target="#seq_'.$count.'" aria-expanded="true">';

	  
	  if ($db_type == 'protein_fasta'){
		  $blast_category = "Protein";
	  }elseif($db_type == 'transcript_fasta'){
		  $blast_category = "Transcript";
	  }elseif($db_type == 'cds_fasta'){
		  $blast_category = "CDS";
	  }
	  
	  echo "<i class=\"fas fa-minus toggle-icon\" style=\"color:#229dff\"></i> $blast_category: $blast_db";

	  echo "</div>";
	  echo "</div>";
	  echo "<div id=\"seq_$count\" class=\"collapse show\";>";
	  echo '<div class="card-body" style="padding-left: 20px;">';
	  echo '<div id="seq_'.$count.'_wrapper" class="dataTables_wrapper dt-bootstrap4 no-footer">';

	  echo "<div class=\"card bg-light\">";
	  echo "<div class=\"card-body copyable\"
          style=\"font-family: monospace; cursor: pointer; white-space: pre-wrap;\"
          data-bs-toggle=\"tooltip\"
          data-bs-placement=\"top\"
          title=\"Click to copy\">".implode("<br>", $ret)."</div>";
	  echo "</div><br>";
	  echo '</div>
    </div>
  </div>
</div>';
  }
  $ret=null;
}


?>
<!---<br>
</div>
-->
      </div>
    </div>
  </div>
</div>


<script>
document.addEventListener("DOMContentLoaded", function () {
    const copyables = document.querySelectorAll(".copyable");

    copyables.forEach(el => {
        // Initialize tooltip
        const tooltip = new bootstrap.Tooltip(el, {
            trigger: "hover",
            title: "Click to copy",
            placement: "top"
        });

        let resetTooltipTimeout;

        el.addEventListener("click", function () {
            const text = el.innerText.trim();

            navigator.clipboard.writeText(text).then(() => {
                // Change color to indicate copy
                el.classList.add("bg-success", "text-white");

                // Update tooltip text
                tooltip.setContent({ '.tooltip-inner': 'Copied!' });
                tooltip.show();

                // Clear previous timeout if exists
                if (resetTooltipTimeout) clearTimeout(resetTooltipTimeout);

                // Reset tooltip and color after 1.2 seconds
                resetTooltipTimeout = setTimeout(() => {
                    el.classList.remove("bg-success", "text-white");
                    tooltip.setContent({ '.tooltip-inner': 'Click to copy' });
                }, 1200);

            }).catch(err => console.error("Copy failed:", err));
        });

        el.addEventListener("mouseleave", function () {
            // If the timeout hasn't triggered yet, keep tooltip until delay
            if (!resetTooltipTimeout) {
                el.classList.remove("bg-success", "text-white");
                tooltip.setContent({ '.tooltip-inner': 'Click to copy' });
            }
        });
    });
});
</script>

