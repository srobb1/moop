<?php include realpath('../../header.php'); ?>
<?php include_once realpath("$root_path/easy_gdb/tools/common_functions.php");?>

<div class="page_container" style="margin-top:40px">
<div class="data_table_frame">

<?php
#$gNamesArr=array_filter(explode("\n",trim($_GET["txtGenes"])),function($gName) {return ! empty($gName);});
# print_r($gNamesArr); 
$gNamesArr = array_filter(
    array_map('trim', explode("\n", trim($_GET["txtGenes"]))),
    function($gName) { return $gName !== ''; }
);
$annotations = isset($_GET['annotations']) 
    ? array_map('intval', $_GET['annotations'])   // cast to integer for safety
    : [];

if(sizeof($gNamesArr)==0) {
	echo "<h1>No genes to search provided.</h1>";
}
else {
  if ($database_type == 'sqlite'){
    $db = $sqlite_db_path;
  }
  
  $placeholdersNames = implode(',', array_fill(0, count($gNamesArr), '?'));
  $placeholdersAnn = implode(',', array_fill(0, count($annotations), '?'));
  $query="SELECT a.annotation_accession, a.annotation_description, aso.annotation_source_id, aso.annotation_type, aso.annotation_source_name, f.feature_uniquename, f.feature_name, f.feature_type, f.feature_description,o.genus, o.species, o.subtype, o.common_name from organism o, annotation_source aso, annotation a, feature_annotation fa, feature f  where f.feature_id = fa.feature_id and fa.annotation_id = a.annotation_id and a.annotation_source_id = aso.annotation_source_id and f.organism_id = o.organism_id and f.feature_uniquename in($placeholdersNames) and aso.annotation_source_id in ($placeholdersAnn)";
  $params = array_merge( $gNamesArr,$annotations);
  $results = fetchData($query, $params, $db);
  
  if ($results) {
    // Printing results in HTML
    echo "<table id=\"tblAnnotations_1\" class=\"table annot_table\">\n";
    echo "<thead><tr>\n";
    echo "<th>Species</th>\n";
    echo "<th>Feature Type</th>\n";
    echo "<th>Feature ID</th>\n";
    echo "<th>Feature Name</th>\n";
    echo "<th>Description</th>\n";
    echo "<th>Annotation Source</th>\n";
    echo "<th>Annotation ID</th>\n";
    echo "<th>Annotation Desciption</th>\n";
    echo "</tr></thead>\n";
    echo "<tbody>\n";
    echo "<div id=\"load_1\" class=\"loader\"></div>";
  
    foreach ($results as $row) {
#	if (!in_array($row["annotation_source_id"], $annotations)) {
#          continue; // skip this iteration
#        }
        $found_unique_name = $row["feature_uniquename"];
        $found_name = $row["feature_name"];
        $found_type = $row["feature_type"];
        $found_desc = $row["feature_description"];
        $found_annotation_source = $row["annotation_source_name"];
        $found_hit_id = $row["annotation_accession"];
        $found_hit_description= $row["annotation_description"];
  
        $genus = $row["genus"];
        $species = $row["species"];
        $species_subtype = $row["subtype"];
        $common_name = $row["common_name"];
        if ($species_subtype and $species_subtype != 'NULL'){
           $species = "$species $species_subtype";
        }
  
        echo "<tr>\n";
        echo "  <td>$genus $species</td>\n";
        echo "  <td>$found_type</td>\n";
        echo "  <td class=\"td-tooltip\" title=\"$common_name\"><a href=\"/$group/tools/search/parent.php?name=$found_unique_name\" target=\"_blank\">$found_unique_name</a></td>\n";
        echo "  <td>$found_name</td>\n";
        echo "  <td>$found_desc</td>\n";
        echo "  <td style=\"white-space: nowrap;\">$found_annotation_source</td>\n";
        echo "  <td style=\"text-align:right\">$found_hit_id</td>\n";
        echo "  <td>$found_hit_description</td>\n";
       
        echo "</tr>\n";
    }
    echo "</tbody>\n</table>\n";
  }
  else {
    echo "<p>No results found.</p>\n";
  }
  echo  "  </div>";
  echo "</div>";
 } 
  ?>
<br>
<br>
<br>

<!-- CSS DATATABLE -->
<style>

  .td-tooltip {
      cursor: pointer;
    }

</style>

<!-- JS DATATABLE -->
<script src="../../js/datatable.js"></script>
<script type="text/javascript">


$(document).ready(function(){

  $('#Annot_table_1').addClass('show');
  $('#load_1').remove();
  $('#tblAnnotations_1').css("display","table");
  datatable("#tblAnnotations_1",'1');

$(".collapse").on('shown.bs.collapse', function(){
      var id=$(this).attr("id");
      id=id.replace("Annot_table_","");

  $('#load_'+id).remove();
  $('#tblAnnotations_'+id).css("display","table");
  datatable("#tblAnnotations_"+id,id);


  $(".td-tooltip").tooltip();
});
});

</script>


<?php include_once realpath("$easy_gdb_path/footer.php");?>
