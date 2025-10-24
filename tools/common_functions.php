<?php


function test_input($data) {
  $data = stripslashes($data);
  $data = preg_replace('/[\<\>]+/','',$data);
  $data = htmlspecialchars($data);

  return $data;
}

function test_input2($data) {
  $data = preg_replace('/[\<\>\t\r\;]+/', '', $data);
  $data = htmlspecialchars($data);
  if (preg_match('/\s+/', $data)) {
    $data_array = explode(' ', $data, 99);
    foreach ($data_array as $key => &$value) {
      if (strlen($value) < 3) {
       unset($data_array[$key]);
       }
     }
     $data = implode(' ',$data_array);
 }
 $data = stripslashes($data);
  return $data;
}

function get_dir_and_files($dir_name) {
    $file_array = array();

    $pattern='/^\./';
    if (is_dir($dir_name)){
      if ($dh = opendir($dir_name)){
        while (($file_name = readdir($dh)) !== false){
          $is_not_file = preg_match($pattern, $file_name, $match);
          if (!$is_not_file) {
            // echo $file_name."<br>";
            array_push($file_array,$file_name);
          }
        }
      }
    }

    rsort($file_array);
    return $file_array;
}

function getDbConnection($dbFile){
      ## would be good to send db type in with the function call so that if we set up functionality with 
	# more db types we could make tht work here
      try {
          $dbh = new PDO("sqlite:" . $dbFile);
          $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
          return $dbh;
      } catch (PDOException $e) {
          die("Database connection failed: " . $e->getMessage());
      }
}

function fetchData($sql, $params = [], $dbFile) {
    try {
        $dbh = getDbConnection($dbFile);
        $stmt = $dbh->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $dbh = null; // Close the connection
	//var_dump($result);
        return $result;
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
}



function getAncestors($feature_uniquename, $dbFile ) {
  $ancestors = [];
  $query = "SELECT feature_id, feature_uniquename, feature_type, parent_feature_id FROM feature WHERE feature_uniquename = ?";
  $results = fetchData($query,[$feature_uniquename], $dbFile);
  foreach ($results as $row){ 
    $ancestors[] = $row;
    while ($row['parent_feature_id']) {
      $query = "SELECT feature_id, feature_uniquename, feature_type, parent_feature_id FROM feature WHERE feature_id = ?";
      $parent_results = fetchData($query,[$row['parent_feature_id']], $dbFile);
      $parent_row = array_shift($parent_results);
      if ($parent_row) {
        $ancestors[] = $parent_row;
	$row = $parent_row;  // Update row to be the parent for the next iteration
      } else {
         break;
      }
    }
  }
  # [[feature_id, feature_uniquename, feature_type, parent_feature_id],[feature_id, feature_uniquename, feature_type, parent_feature_id],..,]
  # [self,parent,grand-parent,great-grand-parent]
  return $ancestors;
}

function getAnnotations($feature_id,$dbFile,$annotation_type){
  $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_type, a.annotation_accession, a.annotation_description, fa.score, fa.date, ans.annotation_source_name, ans.annotation_accession_url, ans.annotation_type
    FROM annotation a, feature f, feature_annotation fa , annotation_source ans, genome g, organism o
    WHERE f.organism_id = o.organism_id
      AND f.genome_id = g.genome_id
      AND ans.annotation_source_id = a.annotation_source_id
      AND f.feature_id = fa.feature_id
      AND fa.annotation_id = a.annotation_id
      AND f.feature_id = ?
      AND ans.annotation_type = ?";

   $params = [$feature_id,$annotation_type];
   $results = fetchData($query, $params, $dbFile);
   return $results;
}
function generateAnnotationTableHTML($results,$uniquename,$type,$count,$annotation_type,$desc){
 // generateAnnotationTableHTML($results,$child_uniquename,$child_type,$count);
 $html = '';	


 if (count($results) > 0) {
   $html .= '<div class="card-header">';
   #$html .=  "<div class=\"collapse_section pointer_cursor\" data-toggle=\"collapse\" data-target=\"#Annot_table_$count\" aria-expanded=\"true\"><i class=\"fas fa-minus toggle-icon\" style=\"color:#229dff\"></i>  <span class=\"alert alert-warning\">$annotation_type</span></div>";
   #$html .= "<div><p>$desc</p></div>";
$html .=  "<div class=\"collapse_section pointer_cursor\" data-toggle=\"collapse\" data-target=\"#Annot_table_$count\" aria-expanded=\"true\">
    <i class=\"fas fa-minus toggle-icon\" style=\"color:#229dff\"></i>  
    <span class=\"alert alert-warning\">$annotation_type</span>
    <i class=\"fas fa-info-circle ml-2\" 
        data-toggle=\"tooltip\" 
        data-html=\"true\" 
        data-placement=\"right\" 
        title=\"$desc\" 
        style=\"color:#007bff; cursor: pointer;\">
    </i>
</div>";


   // Printing results in HTML
   $html .= "<div id=\"Annot_table_$count\" class=\"collapse show\"><table id=\"tblAnnotations_$count\" class=\"tblAnnotations table table-striped table-bordered\"style=\"display:none\">\n";
   $html .= "<div id=\"load_$count\" class=\"loader_$count\"></div>";
   $html .= "<thead>\n";
   $html .= "  <tr>\n";
   
   $html .= "    <th>Feature ID</th>\n";
   $html .= "    <th>Feature Type</th>\n";
   $html .= "    <th>Annotation Accession</th>\n";
   $html .= "    <th>Annotation Description</th>\n";
   $html .= "    <th>Score</th>\n";
   $html .= "    <th>Annotation Source</th>\n";
   $html .= "    <th>Annotation Type</th>\n";
   $html .= "  </tr>\n";
   $html .= "  <tbody>\n";
   foreach ($results as $row) { 
       $hit_id = $row['annotation_accession'];
       $hit_description = $row['annotation_description'];
       $hit_score = $row['score'];
       $analysis_date = $row['date'];
       $annotation_source = $row['annotation_source_name'];
       $annotation_type = $row['annotation_type'];
       $annotation_accession_url = $row['annotation_accession_url'];
       $hit_id_link = $annotation_accession_url . $hit_id;
       $hit_id_link = str_replace(' ', '', $hit_id_link);

       $html .= "  <tr>\n";
       $html .= "    <td>$uniquename</td>\n";
       $html .= "    <td>$type</td>\n";
       $html .= "    <td><a href=\"$hit_id_link\" target=\"_blank\">$hit_id</a></td>\n";
       $html .= "    <td>$hit_description</td>\n";
       $html .= "    <td>$hit_score</td>\n";
       $html .= "    <td>$annotation_source</td>\n";
       $html .= "    <td>$annotation_type</td>\n";
        $html .= "  </tr>\n";
  }

  $html .= "</tbody>\n";
  $html .= "</table>\n\n";
  $html .= "</br>\n";
  $html .= "</br>\n";
  $html .= "</div>\n";
  $html .= "</div>\n";
  $html .= "</br>\n";
 }
  return $html;


}
function getChildren($feature_id, $dbFile) {
    $children = [];
    $query = "SELECT feature_id, feature_uniquename, feature_type, parent_feature_id 
              FROM feature WHERE parent_feature_id = ?";
    
    $results = fetchData($query, [$feature_id], $dbFile);

    foreach ($results as $row) {
        $children[] = $row;
        // Recursively fetch children of this child
        $child_descendants = getChildren($row['feature_id'], $dbFile);
        $children = array_merge($children, $child_descendants);
    }

    # [[feature_id, feature_uniquename, feature_type, parent_feature_id], [child1], [child2], ..., [grandchild1], ...]
    return $children;
}

function generateFeatureTreeHTML($feature_id, $dbFile) {
    #$top_query = "SELECT feature_id, feature_uniquename, feature_type from feature WHERE feature_id = ?" ;
    #$top_results = fetchData($top_query, [$feature_id], $dbFile);
    #$top_row = array_shift($top_results);

    $query = "SELECT feature_id, feature_uniquename, feature_type, parent_feature_id 
              FROM feature WHERE parent_feature_id = ?";
    $results = fetchData($query, [$feature_id], $dbFile);

    if (empty($results)) {
        return ''; // No children, stop recursion
    }
    $html = "<ul>";
    #$html .= "<li>{$top_row['feature_uniquename']} ({$top_row['feature_type']})";
    #$html .= "  <ul>";
    foreach ($results as $row) {
        $html .= "<li>{$row['feature_uniquename']} ({$row['feature_type']})";
        $html .= generateFeatureTreeHTML($row['feature_id'], $dbFile); // Recursive call
        $html .= "</li>";
    }
    $html .= "</ul>";
    #$html .= "  </li></ul>";

    return $html;
}

function buildLikeConditions1(string $input, array $columns, bool $quoted = false): string {
    // Trim and normalize spaces
    $input = trim(preg_replace('/\s+/', ' ', $input));

    // If quoted = false, split input on spaces
    $terms = $quoted ? [$input] : preg_split("/\s+/", $input) ;

    $conditions = [];
    foreach ($terms as $term) {
        $term = trim($term);
        if ($term === '') continue;

        $likeParts = [];
        foreach ($columns as $col) {
            // Escape % and _ to avoid wildcard issues
            $safeTerm = str_replace(['%', '_'], ['\%', '\_'], $term);
            $likeParts[] = "$col LIKE '%$safeTerm%'";
        }

        // OR across all columns for this term
        $conditions[] = '(' . implode(' OR ', $likeParts) . ')';
    }

    // AND across all terms
    return implode(' AND ', $conditions);
}

function buildLikeConditions($columns, $search, $quoted = false) {
    $conditions = [];
    $params = [];

    // If quoted, treat the search as a single phrase
    if ($quoted) {
        foreach ($columns as $col) {
            $searchConditions[] = "$col LIKE ?";
            $params[] = "%" . $search . "%";
        }
        // Each word can match any column
        $conditions[] = "(" . implode(" OR ", $searchConditions) . ")";
    } else {
        // Split on whitespace
        $terms = preg_split('/\s+/', trim($search));
        foreach ($terms as $term) {
            $termConditions = [];
            foreach ($columns as $col) {
                $termConditions[] = "$col LIKE ?";
                $params[] = "%" . $term . "%";
            }
            // Each word can match any column
            $conditions[] = "(" . implode(" OR ", $termConditions) . ")";
        }
    }

    // Join all terms with AND (every word must match something)
    $sqlFragment = implode(" AND ", $conditions);

    return [$sqlFragment, $params];
}


?>

