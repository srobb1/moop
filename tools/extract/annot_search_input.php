<!-- HEADER -->
<?php include_once realpath("../../header.php");?>
<?php include_once realpath("../modal.html");?>
<!-- INFO -->
<?php include_once realpath("search_info_modal.php");?>
<?php include_once realpath("$root_path/easy_gdb/tools/common_functions.php");?>

<!-- HELP -->
<div class="margin-20">
  <a class="float-right" href="/easy_gdb/help/01_search.php" target="blank"><i class='fa fa-info' style='font-size:20px;color:#229dff'></i> Help</a>
</div>
<br>
<h3 class="text-center">Annotation Extraction</h3>

<!-- INPUT FORM -->
<div class="form margin-20">
  <div style="margin:auto; max-width:900px">

<?php
  if ($file_database) {
    echo "<form id=\"egdb_annot_file_form\" action=\"annot_output_file.php\" method=\"get\">";
  } else {
    echo "<form id=\"egdb_annot_file_form\" action=\"annot_output_db.php\" method=\"get\">";
  }
?>


    <!-- FORM OPPENED -->
    <div class="form-group">
      <label for="annot_file_box" style="font-size:16px">Paste a list of gene IDs</label>
      <textarea type="search_box" class="form-control" id="annot_file_box" name="txtGenes" rows="5" style="border-color: #666"><?php echo "$input_gene_list"; ?></textarea>
    </div>


    <!-- IS BETTER TO SET IN ANOTHER FILE -->
    <?php
      if ($file_database) {
## fill in the appropriate code from the orginal annotation extration script
      }//if file_database
      else {
          if ($database_type == 'sqlite'){
            $db = $sqlite_db_path;
          }
          $query = "SELECT annotation_source_id, annotation_source_name, annotation_type
                    FROM annotation_source
                    ORDER BY annotation_type, annotation_source_name
                    ";
          $results = fetchData($query,[], $db);
	  echo "<label for=\"annotations\">Limit Search to:</label>";
	  $current_type = null;
$group_index = 0; // unique ID for each group
foreach ($results as $row) {
    $id    = htmlspecialchars($row['annotation_source_id']);
    $type  = htmlspecialchars($row['annotation_type']);
    $name  = htmlspecialchars($row['annotation_source_name']);

    // When the annotation type changes, start a new section
    if ($type !== $current_type) {
        // Close previous section if any
        if ($current_type !== null) {
            echo "</div></div>";
        }

        $group_index++;
        $group_id = "annotation_group_$group_index";

        // Start a new section
        echo "<div class='annotation-group mb-3' id='$group_id'>";
        echo "<div class='d-flex justify-content-between align-items-center'>";
        echo "<h5 class='mb-0'>" . htmlspecialchars($type) . "</h5>";
        echo "<button type='button' class='btn btn-sm btn-link p-0 toggle-all' data-group='$group_id'>Select/Deselect All</button>";
        echo "</div>";
        echo "<div class='row mt-2'>";
        $current_type = $type;
    }

    // Output the checkbox
    echo "<div class='col-md-4'>";
    echo "  <div class='form-check'>";
    echo "    <input class='form-check-input annotation_checkbox' type='checkbox' name='annotations[]' value='$id' id='annotation_$id' checked>";
    echo "    <label class='form-check-label' for='annotation_$id'>" . htmlspecialchars($name) . "</label>";
    echo "  </div>";
    echo "</div>";
}

// Close the last group
if ($current_type !== null) {
    echo "</div></div>";
}
	  # echo "<div class='row'>";
         # foreach ($results as $row){
         #   $id = htmlspecialchars($row['annotation_source_id']);   // use organism_id
         #   $label = htmlspecialchars($row['annotation_type'] .": ". $row['annotation_source_name'] );
         #   echo "<div class='col-md-4'>";
         #   echo "<div class=\"form-check\">";
         #   echo "  <input class=\"form-check-input annotation_checkbox\" type=\"checkbox\" name=\"annotations[]\" value=\"$id\" id=\"org_$id\" checked>";
         #   echo "  <label class=\"form-check-label\" for=\"annotation_$id\">$label</label>";
         #   echo "</div>";
         #   echo "</div>";
         # } 

#echo "</div>"; // close row


      }
    ?>

    <br>
    <button type="submit" class="btn btn-info float-right" form="egdb_annot_file_form" style="margin-top: -5px">Search</button>
    <br>
    <br>
    <br>
    </form>
  </div>
</div>

<!-- FOOTER -->
<?php include_once realpath("$easy_gdb_path/footer.php");?>


<!-- IS BETTER TO ADD TO THE GENERAL CSS -->
<style>  
  .info_icon {
    background-color:#4387FD;
    border-radius:20px;
    vertical-align: top;
    border:0px;
    display:inline-block;
    color:#ffffff;
    font-family:"Georgia",Georgia,Serif;
    font-size:12px;
    font-weight:bold;
    font-style:normal;
    width:18px;
    height:18px;
    line-height:0px;
    text-align:center;
  }
  .info_icon:hover {
    background-color:#5EA1FF;
    color:#0000CC;
  }

  .info_icon:active {
    position:relative;
    top:1px;
  }
</style>


<!-- JAVASCRIPT -->
<script> 
$(document).ready(function () {
  $('#egdb_annot_file_form').submit(function() {
    var gene_id = $('#annot_file_box').val();
    var data_set_selected = false;
    var file_database = "<?php echo $file_database; ?>";
    var select_field = $('.sample_checkbox').length > 0;
    var annotation_selected = false;

    if (select_field) {
      $('.sample_checkbox').each(function() {
        if ($(this).is(':checked')) {
          data_set_selected = true;
          return false;
        }
      });
    }

    // Forms
    if ($('.annotation_checkbox:checked').length === 0) {
      $("#search_input_modal").html("At least one annotation needs to be selected");
      $('#no_gene_modal').modal();
      return false; // stop form submission
    }
    if (!gene_id) {
      $("#search_input_modal").html( "No input provided in the search box" );
      $('#no_gene_modal').modal();
      return false;
    }
    else if (gene_id.length < 3) {
      $("#search_input_modal").html( "Input is too short, please provide a longer term to search" );
      $('#no_gene_modal').modal();
      return false;
    }
    else if (file_database === '1' && !data_set_selected && select_field) {
      $("#search_input_modal").html( "No annotation file/s selected" );
      $('#no_gene_modal').modal();
      return false;
    }
    else {
      return true;
    };
  });
});


document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll(".toggle-all").forEach(button => {
        button.addEventListener("click", function() {
            const groupId = this.getAttribute("data-group");
            const checkboxes = document.querySelectorAll("#" + groupId + " .annotation_checkbox");
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);

            checkboxes.forEach(cb => cb.checked = !allChecked);
        });
    });
});

</script>

