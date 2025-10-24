<?php include_once realpath("../../header.php");?>
<?php include_once realpath("search_info_modal.php");?>
<div class="page_container">


<?php

$raw_input = $_GET["search_keywords"];
$quoted_search = 0;
$organisms = $_GET['organism'];
if ( preg_match('/^".+"$/',$raw_input ) ) {
  $quoted_search = 1;
  $raw_input = str_replace(['"', "'"], '', $raw_input);  
}

function test_search_input($data,$quoted_search) {
  
  $data = preg_replace('/[\<\>\t\;]+/',' ',$data);
  $data = htmlspecialchars($data);
  
  if ( preg_match('/\s+/',$data) ) {
    $data_array = explode(' ',$data,99);
    foreach ($data_array as $key=>&$value) {
        if (strlen($value) < 3 && !$quoted_search){
            unset($data_array[$key]);
        }
    }

    $data = implode(' ',$data_array);
  }

  $data = stripslashes($data);

  return $data;
}
$search_input = test_search_input($raw_input,$quoted_search);
// $max_row = 25;


#echo "\n<br><h3>Search Input ";
#echo "<button type=\"button\" class=\"info_icon\" data-toggle=\"modal\" data-target=\"#search_help\">i</button>\n";
#echo "</h3>";
#echo "<div class=\"card bg-light\"><div class=\"card-body\">$search_input</div></div><br>\n";


?>
 
<?php include_once realpath("search_annot.php");?>



<br>
<br>
</div>

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
