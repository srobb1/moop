<?php include realpath("../header.php"); ?>
<?php include realpath('modal.html'); ?>
<?php include_once realpath("$root_path/easy_gdb/tools/common_functions.php");?>

<div class="margin-20">
  <a class="float-right" href="/easy_gdb/help/04_sequence_extraction.php" target="_blank"><i class='fa fa-info' style='font-size:20px;color:#229dff'></i> Help</a>
</div>
<br>




<?php

//if (isset($_GET['uniquenames'])) {
//  // Retrieve and sanitize the feature_ids parameter
//  $uniquenames_string = filter_input(INPUT_GET, 'uniquenames', FILTER_SANITIZE_STRING);

  // Convert string to an array
//  $uniquenames_array = explode(",", $uniquenames_string);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = isset($_POST['uniquenames']) ? $_POST['uniquenames'] : '';
} else {
    $ids = isset($_GET['uniquenames']) ? $_GET['uniquenames'] : '';
}
if (!empty($ids)) {
  $uniquenames = array_filter(explode(",", $ids));
  if (!$file_database){
    if ($database_type == 'sqlite'){
      $db = $sqlite_db_path;
    }

    // Generate the correct number of placeholders
    $placeholders = implode(',', array_fill(0, count($uniquenames), '?'));

    $retrieve_these = []; 
    $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_type
       FROM feature f
       WHERE f.feature_uniquename IN ($placeholders)";
    $results = fetchData($query, $uniquenames, $db);
    $msgs=[];
    foreach ($results as $row){
      $uniquename = $row['feature_uniquename'];
      $feature_id= $row['feature_id'];
      $type = $row['feature_type'];
      if ($type == 'mRNA'){
        $retrieve_these[] = $uniquename;
      }elseif($type == 'gene'){
        $child_query = "SELECT f.feature_uniquename, f.feature_type
                      FROM feature f
                      WHERE f.parent_feature_id = ?
                        AND f.feature_type == 'mRNA'";
        $params = [$feature_id];
        $child_results = fetchData($child_query, $params, $db);
        $msgs[]="$uniquename is a gene. Retrieving its child mRNA sequences instead.";

        foreach ($child_results as $child_row){
          $child_uniquename = $child_row['feature_uniquename'];
          $retrieve_these[] = $child_uniquename;
        }
      }
    }
    $retrieve_these = array_unique($retrieve_these);
    $input_id_list = implode(",",$retrieve_these);
    $message = implode("<br>\n",$msgs);
  }
}
?>

<div id="dlgDownload">
  <br>
  <h3 class="text-center">Gene Sequence Downloading</h3>
  <div class="form margin-20" style="margin:auto; max-width:900px">
    <form id="download_fasta_form" action="blast/blastdbcmd.php" method="post">
      <label for="txtDownloadGenes">Paste a list of gene IDs</label>
      <textarea class="form-control" id="txtDownloadGenes" rows="8" name="gids">
<?php echo "$input_id_list"; ?>
      </textarea>
      <br>
      <?php if ($message) echo "<p class=\"text-warning\">$message</p>"; ?>

      <div class="form-group">
        <?php include_once 'blast/blast_dbs_select.php';?>
      </div>

      <button class="button btn btn-info float-right" id="btnSend" type="submit" form="download_fasta_form" formmethod="post">Download</button>
      </form>
      <br>
      <br>

  </div>

</div>

<div id="stderr_container" style="display:none; border:1px solid #f5c6cb; background:#f8d7da; color:#721c24; border-radius:5px; margin-top:10px;">
    <div id="stderr_header" style="padding:5px 10px; font-weight:bold; cursor:pointer;">
        Sequence Retrieval Errors (click to toggle). Check your Selected Dataset.
    </div>
    <textarea id="stderr_area" readonly style="width:100%;height:200px;border:none;background:#f8d7da;padding:5px;resize:vertical;"></textarea>
</div>
<?php include realpath('../footer.php'); ?>


<style>
  .margin-20 {
    margin: 20px;
  }
</style>

<script>
$(document).ready(function () {

    // Toggle the error container manually
    $('#stderr_header').click(function() {
        $('#stderr_area').slideToggle();
    });

    $('#download_fasta_form').submit(function (e) {
        e.preventDefault(); // prevent normal form submit

        var gene_lookup_input = $('#txtDownloadGenes').val().trim();
        var gene_count = (gene_lookup_input.match(/\n/g)||[]).length + (gene_lookup_input ? 1 : 0);

        // Input validation
        var max_input = "<?php echo $max_extract_seq_input ?>";
        if (!max_input) max_input = 1000;

        if (gene_count > max_input) {
            $("#search_input_modal").html("A maximum of " + max_input + " sequences can be provided as input, your input has: " + gene_count);
            $('#no_gene_modal').modal();
            return false;
        }

        if (gene_count === 0) {
            $("#search_input_modal").html("No gene IDs were provided as input");
            $('#no_gene_modal').modal();
            return false;
        }

        // Hide previous errors
        $('#stderr_container').hide();
        $('#stderr_area').hide().text('');

        // Send AJAX request
        $.post('blast/blastdbcmd.php', $(this).serialize(), function(data){
            var hasError = false;

            if(data.stderr && data.stderr.length > 0){
                $('#stderr_area').text(data.stderr);
                $('#stderr_container').slideDown();
                $('#stderr_area').slideDown();
                hasError = true;
            }

            if(data.stdout && data.stdout.length > 0){
                // Trigger download for stdout
                var blob = new Blob([data.stdout], {type: 'text/plain'});
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'egdb_sequences.fasta';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            }

            if(!hasError){
                $('#stderr_container').hide();
            }

        }, 'json').fail(function(jqXHR, textStatus, errorThrown){
            $('#stderr_area').text("AJAX error: " + textStatus + " - " + errorThrown);
            $('#stderr_container').slideDown();
            $('#stderr_area').slideDown();
        });

    });

});
</script>

