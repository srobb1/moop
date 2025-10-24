<!-- HEADER -->
<?php include_once realpath("../../header.php");?>
<?php include_once realpath("$root_path/easy_gdb/tools/common_functions.php");?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">


<!-- HELP -->
<div class="margin-20">
  <a class="float-right" href="help/00_help.php"><i class='fa fa-info' style='font-size:20px;color:#229dff'></i> Help</a>
</div>


<div class="page_container">
<br><br>

<?php

echo "<div class=\"card bg-light\">";
echo "<div id=\"query_gene\" class=\"card-body\">".test_input($_GET["name"])."</div>";
echo "</div>";
echo "<br><hr><br>";

// Get variables from config file 
if ($database_type == 'sqlite'){
  $db = $sqlite_db_path;
}

$analysis_order_list = $analysis_order;


// sent from form or in url http://localhost:8800/easy_gdb/tools/search/parent.php?name=ACA1_PVKU01000420.1_000002.1
$uniquename = test_input($_GET["name"]);


//getting array $parents from config file
$ancestors = getAncestors($uniquename, $db );
// [[feature_id, feature_uniquename, feature_type, parent_feature_id],[feature_id, feature_uniquename, feature_type, parent_feature_id],..,]
// [self,parent,grand-parent,great-grand-parent]

// Save the highest ancestor with type in $parents in these variables
[$ancestor_feature_id, $ancestor_feature_uniquename, $ancestor_feature_type] = [ '','',''];

if (count($ancestors) == 1){
  // self only, no parents
  $ancestor = $ancestors[0];
  $ancestor_feature_id = $ancestor['feature_id'];
  $ancestor_feature_type = $ancestor['feature_type'];
  $ancestor_feature_uniquename = $ancestor['feature_uniquename'];
  $ancestor_parent_feature_id = $ancestor['parent_feature_id'];
}elseif(count($ancestors) > 1){
  // self, plus at least one ancestor
  foreach ($ancestors as $ancestor){
    $ancestor_feature_id = $ancestor['feature_id'];
    $ancestor_feature_type = $ancestor['feature_type'];
    $ancestor_feature_uniquename = $ancestor['feature_uniquename'];
    $ancestor_parent_feature_id = $ancestor['parent_feature_id'];
    if (in_array($ancestor_feature_type, $parents)) {
       // Stop: we reached our valid parent type for a page
       break;
    }
  }
}
$family_feature_ids = [];

// Performing SQL query to get info associated with found Parent ID
$query = "SELECT f.feature_id, f.feature_uniquename, f.feature_name, f.feature_description, f.feature_type, f.parent_feature_id, o.genus, o.species, o.subtype, o.common_name, o.taxon_id, g.genome_accession, g.genome_name
  FROM genome g, organism o,  feature f
  WHERE f.organism_id = o.organism_id  
    AND f.genome_id = g.genome_id
    AND f.feature_id = ?";

$params = [$ancestor_feature_id];
$results = fetchData($query, $params, $db);

// Get all info about Highest Parent
if (count($results) == 0){ 
  die("The gene $uniquename was not found in the database. Please, check the spelling carefully or try to find it in the search tool.");
}elseif(count($results) == 1 ){
  $row = array_shift($results);
  $feature_id =  $row['feature_id'] ;
  $feature_uniquename =  $row['feature_uniquename'] ;
  $parent_id =  $row['parent_feature_id'] ;
  $name = $row['feature_name'];
  $description = $row['feature_description'];      
  $genus =  $row['genus'];
  $species =  $row['species'];
  $species_subtype =  $row['subtype'];
  $type =  $row['feature_type'];
  $common_name =  $row['common_name'];
  $genome_accession = $row['genome_accession'];
  $genome_name = $row['genome_name'];

  //if ($name and $description and ($name != $feature_uniquename) and ($name != $description){
  //  $description = "$name: $description";
  //}

  $family_feature_ids[] = $feature_id;
  $retrieve_these_seqs = [$feature_uniquename];

//      <i class="fas fa-sort" style="color:#229dff"></i> Overview
echo '
<div class="card">
  <div class="card-header">
    <div class="collapse_section pointer_cursor" data-toggle="collapse" data-target="#Overview" aria-expanded="true">
      <i class="fas fa-minus toggle-icon" style="color:#229dff"></i> <span class="alert alert-info">Overview</span>
    </div>
  </div>
  <div id="Overview" class="collapse show">
    <div class="card-body" style="padding-left: 20px;">
      <div id="Overview_wrapper" class="dataTables_wrapper dt-bootstrap4 no-footer">
        <!-- Existing content -->
';

   //Parent Info
   echo "<div class=\"container mt-4\" style=\"margin-left: 0;\">";
   echo "<table class=\"table\" style=\"text-align: left;\">";
   echo "<tr><th style=\"text-align: left;\">Uniquename</th><td>$feature_uniquename</td></tr>";
   echo "<tr><th style=\"text-align: left;\">Type</th><td>$type</td></tr>";
   echo "<tr><th style=\"text-align: left;\">Name</th><td>$description</td></tr>";
   echo "<tr><th style=\"text-align: left;\">Species</th><td><em>$genus $species</em></td></tr>";
   echo "<tr><th style=\"text-align: left;\">Common Name</th><td>$common_name</td></tr>";
   echo "</table>";
   echo "</div>";

   // Tree
   echo "<div class=\"collapse_section pointer_cursor\" data-toggle=\"collapse\" data-target=\"#tree\" aria-expanded=\"true\"><i class=\"fas fa-minus toggle-icon\" style=\"color:#229dff\"></i> <span class=\"alert alert-info\">Tree</tree></div>";
   echo '
<div id="tree" class="container collapse show" mt-4 style="margin-left: 0;"> 
  <div class="row">
    <div class="col-md-12">
       <ul id="tree1">';
echo "<li><a href=\"#\">{$feature_uniquename} ({$type})</a>"; 
echo generateFeatureTreeHTML($feature_id,$db);
echo '   </li>
       </ul>
    </div>
  </div>
</div>';

  ## send $jb_dataset to include to check to see if a jbrowse dir exists and then display it
  $jb_dataset = "$group/$genome_name";
  $gene_name = $feature_uniquename;
  if (!$rm_jb_frame and $jb_dataset) {
    include_once '../../jb_frame_parent.php';
  }
  echo '
      </div>
    </div>
  </div>
';
}else{ 
  # TO DO: if there is more than one result, need to make a list of the hits with links so the user can pick the correct one
}
$children = getChildren($feature_id,$db);
$count=0;
  //<div id="Annot" class="collapse show">
echo '
<br>
<div class="card">
  <div class="card-header">
    <div class="collapse_section pointer_cursor" data-toggle="collapse" data-target="#Annot" aria-expanded="true">
      <i class="fas fa-minus toggle-icon" style="color:#229dff"></i> <span class="alert alert-info">Annotations</span>
    </div>
  </div>
  <div id="Annot" class="collapse show">
    <div class="card-body" style="padding-left: 20px;">
      <div id="tblAnnotations_1_wrapper" class="dataTables_wrapper dt-bootstrap4 no-footer">
        <!-- Existing content -->
';
// Does the parent have annotaions?
if (count($results) > 0){
  foreach ($analysis_order_list as $annotation_type){
    $count++;
    $annot_results = getAnnotations($feature_id,$db,$annotation_type);
    $annotations = generateAnnotationTableHTML($annot_results,$feaure_uniquename,$type,$count,$annotation_type,$analysis_desc[$annotation_type]);
    echo $annotations;
  }
}
// do the childred have annotations?
$child_count = 0;
foreach ($children as $child){
  $child_count++;
  $child_feature_id = $child['feature_id'];
  $child_uniquename = $child['feature_uniquename'];
  $child_type = $child['feature_type'];
  
echo '<div class="card">';
echo '  <div class="card-header">';
echo "    <div class=\"collapse_section pointer_cursor\" data-toggle=\"collapse\" data-target=\"#child_$child_count\" aria-expanded=\"true\">";
echo "      <i class=\"fas fa-minus toggle-icon\" style=\"color:#229dff\"></i> <span class=\"alert alert-success\" >$child_uniquename ($child_type)</span>";
echo '    </div>';
echo '  </div>';
echo "  <div id=\"child_$child_count\" class=\"collapse show\">";
echo '    <div class="card-body" style="padding-left: 20px;">';
echo "    <div id=\"#child_$child_count\" class=\"dataTables_wrapper dt-bootstrap4 no-footer\">";

  $annotation_count = 0;
  foreach ($analysis_order_list as $annotation_type){
    $annotation_count++;
    $annot_results = getAnnotations($child_feature_id,$db,$annotation_type);
    $annotations =  generateAnnotationTableHTML($annot_results,$child_uniquename,$child_type,$annotation_count,$annotation_type,$analysis_desc[$annotation_type]);
    echo $annotations;
  }
  if (!$annotation_count){
     #echo "<div class=\"collapse_section pointer_cursor\" data-toggle=\"collapse\" data-target=\"#Annot_table_$count\" aria-expanded=\"true\">
     echo "<div class=\"alert\" data-toggle=\"collapse\" data-target=\"#Annot_table_$count\" aria-expanded=\"true\">
	 No annotations loaded for $child_uniquename ($child_type).
      </div>"; 
  }

echo '       </div>';
echo '     </div>';
echo '   </div>';
echo ' </div>';

  $retrieve_these_seqs[]=$child_uniquename;
  
}
// need to add a none found if none are found
echo '
      </div>
    </div>
  </div>
</div>
';

$retrieve_these_seqs = array_unique($retrieve_these_seqs);
sort($retrieve_these_seqs);
$gene_name = implode(",", $retrieve_these_seqs);
echo "<br>\n";
echo "<br>\n";
#include_once '../../gene_seq.php';
include_once '../../display_sequences.php';
?>

<br>
<br>
</div>


<?php include_once '../../footer.php';?>

<script>
  var query_gene = "<?php echo $uniquename ?>";
  var sps_name = "<?php echo $genus . ' ' .  $species ?>";
  var annot_v = "<?php echo $genome_name ?>";
  document.getElementById('query_gene').innerHTML = "Query: "+query_gene+" &nbsp; <i>"+sps_name+"</i> &nbsp; v"+annot_v;
</script>

<!-- JS DATATABLE -->
<script src="../../js/datatable.js"></script>
<script type="text/javascript">


$(document).ready(function() {
    // Expand all collapsible elements
    $(".collapse").each(function() {
        var id = $(this).attr("id");
        var index = id.replace("Annot_table_", "");

        // Manually show each collapse
        $(this).collapse("show");

        // Set up the table inside
        $('#load_' + index).remove();
        $('#tblAnnotations_' + index).css("display", "table");
        datatable("#tblAnnotations_" + index, index);
    });

    // Initialize tooltip after collapses are shown
    $(".td-tooltip").tooltip();
});

$.fn.extend({
    treed: function (o) {

        var openedClass = 'fa-minus';
        var closedClass = 'fa-plus';

        if (typeof o != 'undefined') {
            if (typeof o.openedClass != 'undefined') {
                openedClass = o.openedClass;
            }
            if (typeof o.closedClass != 'undefined') {
                closedClass = o.closedClass;
            }
        }

        // Initialize each of the top-level branches
        var tree = $(this);
        tree.addClass("tree");
        tree.find('li').has("ul").each(function () {
            var branch = $(this); // li with children ul

            // Start with opened icon
            branch.prepend("<i class='indicator fa " + openedClass + "'></i>");
            branch.addClass('branch');

            // Ensure all children are visible by default
            branch.children("ul").show();

            branch.on('click', function (e) {
                if (this == e.target) {
                    var icon = $(this).children('i:first');
                    icon.toggleClass(openedClass + " " + closedClass);
                    $(this).children("ul").toggle();
                }
            });
        });

        // Fire event from dynamically added icon
        tree.find('.branch .indicator').each(function () {
            $(this).on('click', function () {
                $(this).closest('li').click();
            });
        });

        // Fire event to open branch if the li contains an anchor or button instead of text
        tree.find('.branch>a, .branch>button').each(function () {
            $(this).on('click', function (e) {
                $(this).closest('li').click();
                e.preventDefault();
            });
        });
    }
});

// Initialization of treeviews
$('#tree1').treed();


// chnage the '-' icon to '+' when collaped and the reverse
$(document).ready(function () {
  $('.collapse').on('show.bs.collapse', function (e) {
    if (e.target !== this) return;  // Prevent bubbling from children

    $('[data-target="#' + this.id + '"] .toggle-icon')
      .removeClass('fa-plus')
      .addClass('fa-minus');
  });

  $('.collapse').on('hide.bs.collapse', function (e) {
    if (e.target !== this) return;  // Prevent bubbling from children

    $('[data-target="#' + this.id + '"] .toggle-icon')
      .removeClass('fa-minus')
      .addClass('fa-plus');
  });
});


$(function () {
  $('[data-toggle="tooltip"]').tooltip({ html: true });
});

</script>




