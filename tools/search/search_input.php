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
<h3 class="text-center">Gene / Annotation Search</h3>

<!-- INPUT FORM -->
<div class="form margin-20">
  <div style="margin:auto; max-width:900px">

<?php
  if ($file_database) {
    echo "<form id=\"egdb_search_file_form\" action=\"search_output_file.php\" method=\"get\">";
  } else {
    echo "<form id=\"egdb_search_file_form\" action=\"search_output.php\" method=\"get\">";
  }
?>


    <!-- FORM OPPENED -->
    <div class="form-group">
      <label for="search_file_box" style="font-size:16px">Insert a gene ID or annotation keywords</label>
      <button type="button" class="info_icon" data-toggle="modal" data-target="#search_help">i</button>
      <input type="search_box" class="form-control" id="search_file_box" name="search_keywords" style="border-color: #666">
    </div>


    <?php
          if ($database_type == 'sqlite'){
            $db = $sqlite_db_path;
          }
          $query = "SELECT o.organism_id, o.genus, o.species, o.common_name, o.subtype
                    FROM organism o
                    ";
          $results = fetchData($query,[], $db);
	  echo "<label for=\"organism\">Limit Search to:</label>";
          echo "<div class='row'>";
          foreach ($results as $row){
            $id = htmlspecialchars($row['organism_id']);   // use organism_id
            $label = htmlspecialchars($row['common_name'] ." (". ($row['genus']." ".$row['species'] .")" ));
            echo "<div class='col-md-4'>";
            echo "<div class=\"form-check\">";
            echo "  <input class=\"form-check-input organism_checkbox\" type=\"checkbox\" name=\"organism[]\" value=\"$id\" id=\"org_$id\" checked>";
            echo "  <label class=\"form-check-label\" for=\"org_$id\">$label</label>";
            echo "</div>";
            echo "</div>";

          } 

echo "</div>"; // close row


    ?>

    <br>
    <button type="submit" class="btn btn-info float-right" style="margin-top: -5px">Search</button>
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
  $('#egdb_search_file_form').submit(function() {
    var gene_id = $('#search_file_box').val();
    var data_set_selected = false;
    var file_database = "<?php echo $file_database; ?>";
    var select_field = $('.sample_checkbox').length > 0;
    var organism_selected = false;

    if (select_field) {
      $('.sample_checkbox').each(function() {
        if ($(this).is(':checked')) {
          data_set_selected = true;
          return false;
        }
      });
    }

    // Forms
    if ($('.organism_checkbox:checked').length === 0) {
      $("#search_input_modal").html("At least one organism needs to be selected");
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

</script>

