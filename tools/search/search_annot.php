<?php include_once realpath("$root_path/easy_gdb/tools/common_functions.php");?>


<?php
// Performing SQL query

// Get annotation types
#include_once("../get_annotation_types.php");

// only one dbtype configured at the moment, sqlite
// database_type and sqlite_db_path is stored in config
if ($database_type == 'sqlite'){
  $db = $sqlite_db_path;
}
// Build a string of placeholders: ?,?,?...
$organisms_placeholders = implode(',', array_fill(0, count($organisms), '?'));

// variable to determine if user is searching by unqiuename (gene/transcript id) or some other text
$uniquename_search = 0;


$like = '';
$columns = ["a.annotation_description", "f.feature_name", "f.feature_description", "a.annotation_accession"];
$search_type = '';
if ( $quoted_search ) {
  $search_type = "Quoted";
  $search_input = str_replace(['"', "'", '“', '”', '‘', '’'], '', $search_input);
  list($like,$terms) = buildLikeConditions($columns, $search_input, true);
} 
elseif ( preg_match('/\s+/',$search_input) ) {

  $search_type = "Multi-Word";
  $search_input = str_replace(['"', "'", '“', '”', '‘', '’'], '', $search_input);
  list($like,$terms) = buildLikeConditions($columns, $search_input, false);
} 
else { 
  // if not quoted, and only one word, check to see if it is a feature uniquename (unique ID)
  $columns = ["f.feature_uniquename"];
  list($like,$terms) = buildLikeConditions($columns, $search_input, false);
  $query = "SELECT f.feature_uniquename, f.feature_name, f.feature_description, o.genus, o.species, o.common_name, o.subtype, f.feature_type
  FROM feature f,  organism o
  WHERE f.organism_id = o.organism_id
    AND o.organism_id in ($organisms_placeholders)
    AND $like
  ORDER BY f.feature_uniquename";
  $params = array_merge($organisms,$terms);
  $results = fetchData($query, $params, $db);
    
  if (!$results){
    $search_type = "Single-Word";
    $columns = ["a.annotation_description", "f.feature_name", "f.feature_description", "a.annotation_accession"];
    list($like,$terms) = buildLikeConditions($columns, $search_input, false);
  }else{
    $search_type = "Gene/Transcript ID";
    $uniquename_search = 1;
  }
}

$search_terms = explode(" ",$search_input);
if (!$quoted_search){
  $search_terms = implode(" AND ",$search_terms);
}else{
  $search_terms = '"' . $search_input . '"';
}
echo "\n<br><h3>Search Type: $search_type  ";
echo "<button type=\"button\" class=\"info_icon\" data-toggle=\"modal\" data-target=\"#search_help\">i</button>\n";
echo "</h3>";
echo "<div class=\"card bg-light\"><div class=\"card-body\">Searched for: $search_terms</div></div><br>\n";
?>
<div class="collapse_section pointer_cursor" data-toggle="collapse" data-target="#Annot_table_1"><h3>Results found</h3></div>
<div id="Annot_table_1" class="collapse show">
  <br>
  <div class="data_table_frame">
<?php
// if there are no results in the feature_uniquename search, search everything else
if (!$uniquename_search){

  $query = "SELECT f.feature_uniquename, f.feature_name, f.feature_description, a.annotation_accession, a.annotation_description, fa.score, fa.date, ans.annotation_source_name, o.genus, o.species, o.common_name, o.subtype, f.feature_type 
    FROM annotation a, feature f, feature_annotation fa, annotation_source ans, organism o 
    WHERE ans.annotation_source_id = a.annotation_source_id 
      AND f.feature_id = fa.feature_id 
      AND fa.annotation_id = a.annotation_id 
      AND f.organism_id = o.organism_id
      AND o.organism_id in ($organisms_placeholders)
      AND $like 
    ORDER BY f.feature_uniquename";
  $params = array_merge($organisms,$terms); // Pass as array
  $results = fetchData($query, $params, $db);
}

if ($results) {
  // Printing results in HTML
  echo "<table id=\"tblAnnotations_1\" class=\"table annot_table\">\n";
  echo "<thead><tr>\n";
  echo "<th>Species</th>\n";
  echo "<th>Feature Type</th>\n";
  echo "<th>Feature ID</th>\n";
  echo "<th>Feature Name</th>\n";
  echo "<th>Description</th>\n";
  if (!$uniquename_search){
    echo "<th>Annotation Source</th>\n";
    echo "<th>Annotation ID</th>\n";
    echo "<th>Annotation Desciption</th>\n";
  }
  echo "</tr></thead>\n";
  echo "<tbody>\n";
  echo "<div id=\"load_1\" class=\"loader\"></div>";

  foreach ($results as $row) {
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
      if(!$uniquename_search){
        echo "  <td style=\"white-space: nowrap;\">$found_annotation_source</td>\n";
        echo "  <td style=\"text-align:right\">$found_hit_id</td>\n";
        echo "  <td>$found_hit_description</td>\n";
      }
      echo "</tr>\n";
  }
  echo "</tbody>\n</table>\n";
}
else {
  echo "<p>No results found.</p>\n";
}
?>
  </div>
</div>
