function datatable(table_id, select_id) {
  if (!$.fn.DataTable.isDataTable(table_id)) {

    // --- Selection persistence store ---
    var selectedRows = new Set(); // track selected Feature IDs
    var table; // will hold DataTable instance
    var featureIdColumnIndex = 0; // adjust if your feature ID column isn't index 0

    // Add Select column header + checkboxes
    $(table_id + ' thead tr').prepend('<th>Select</th>');
    $(table_id + ' tbody tr').each(function () {
      $(this).prepend('<td><input type="checkbox" class="row-select"></td>');
    });

    // Clone header row for search inputs
    $(table_id + ' thead tr').clone(true).appendTo(table_id + ' thead');
    $(table_id + ' thead tr:eq(0) th').each(function (i) {
      var title = $(this).text();
      if (title !== "Select") {
        $(this).html(
          '<input style="text-align:center; border: solid 1px #808080; border-radius: 4px; width: calc(' +
            title.length +
            'ch + 80px);" type="text" placeholder="Search ' + title + '" />'
        );

        $('input', this).on('keyup change', function () {
          if (table && table.column(i).search() !== this.value) {
            var colIndex = table.colReorder.transpose(i);
            table.column(colIndex).search(this.value).draw();
          }
        });
      } else {
        $(this).html(
          '<button style="width:110px; border-radius: 4px; white-space: nowrap; border: solid 1px #808080; padding: 0;" class="btn btn_select_all" id="toggle-select-btn' +
            select_id +
            '"><span>Select All</span></button>'
        );
      }
    });

    // --- Initialize DataTable ---
    table = $(table_id).DataTable({
      dom: 'Bfrtlpi',
      oLanguage: { sSearch: "Filter by:" },
      pageLength: 25,
      stateSave: false,
      buttons: [
        {
          extend: 'copy',
          exportOptions: { rows: exportSelectedRows },
          action: exportAction('copyHtml5')
        },
        {
          extend: 'csv',
          exportOptions: { rows: exportSelectedRows },
          action: exportAction('csvHtml5')
        },
        {
          extend: 'excel',
          exportOptions: { rows: exportSelectedRows },
          action: exportAction('excelHtml5')
        },
        {
          text: 'FASTA',
          action: fastaExportAction
        },
        {
          extend: 'pdf',
          orientation: 'landscape',
          pageSize: 'LEGAL',
          exportOptions: { rows: exportSelectedRows },
          action: exportAction('pdfHtml5')
        },
        {
          extend: 'print',
          exportOptions: { rows: exportSelectedRows },
          action: exportAction('print')
        },
        'colvis'
      ],
      sScrollX: "100%",
      sScrollXInner: "110%",
      bScrollCollapse: true,
      colReorder: true,
      retrieve: true,
      drawCallback: function () {
        $(".dataTables_filter input").css("border-radius", "5px");
        $("table.dataTable tbody tr").hover(
          function () { $(this).css("background-color", "#d1d1d1"); },
          function () { $(this).css("background-color", ""); }
        );
        restoreSelections();
      }
    });

    // --- Restore selections on redraw ---
    function restoreSelections() {
      if (!table) return;
      table.rows().every(function () {
        var rowData = this.data();
        var rowId =
          featureIdColumnIndex >= 0 ? rowData[featureIdColumnIndex] : this.index();
        var checked = selectedRows.has(rowId);
        $(this.node()).find('input.row-select').prop('checked', checked);
        $(this.node()).toggleClass('selected', checked);
      });
    }

    // --- Checkbox click handler ---
    $(table_id).on('change', '.row-select', function () {
      var $row = $(this).closest('tr');
      var data = table.row($row).data();
      var rowId =
        featureIdColumnIndex >= 0 ? data[featureIdColumnIndex] : table.row($row).index();

      if (this.checked) {
        selectedRows.add(rowId);
        $row.addClass('selected');
      } else {
        selectedRows.delete(rowId);
        $row.removeClass('selected');
      }
    });

//    // --- Select/Deselect all handler ---
//    $('#toggle-select-btn' + select_id).on('click', function (e) {
//      e.preventDefault();
//      var allChecked = selectedRows.size === table.rows().count();
//      if (allChecked) {
//        selectedRows.clear();
//      } else {
//        table.rows().every(function () {
//          var data = this.data();
//          var rowId =
//            featureIdColumnIndex >= 0 ? data[featureIdColumnIndex] : this.index();
//          selectedRows.add(rowId);
//        });
//      }
//      table.draw(false);
//    });


//	  // --- Select/Deselect all handler ---
//$('#toggle-select-btn' + select_id).on('click', function (e) {
//  e.preventDefault();
//
//  var allChecked = selectedRows.size === table.rows().count();
//
//  if (allChecked) {
//    // Deselect all
//    selectedRows.clear();
//    $(this).find('span').text('Select All');
//  } else {
//    // Select all
//    table.rows().every(function () {
//      var data = this.data();
//      var rowId = featureIdColumnIndex >= 0 ? data[featureIdColumnIndex] : this.index();
//      selectedRows.add(rowId);
//    });
//    $(this).find('span').text('Deselect All');
//  }
//
//  table.draw(false);
//});
	  // Put these helper functions somewhere inside datatable(), e.g. after restoreSelections()

function updateToggleButton() {
  var visibleApi = table.rows({ search: 'applied' });
  if (!visibleApi || visibleApi.count() === 0) {
    $('#toggle-select-btn' + select_id).find('span').text('Select All');
    return;
  }

  var allVisibleSelected = true;
  visibleApi.every(function() {
    var data = this.data();
    var rowId = featureIdColumnIndex >= 0 ? data[featureIdColumnIndex] : this.index();
    if (!selectedRows.has(rowId)) {
      allVisibleSelected = false;
      return false; // stop iterating
    }
  });

  $('#toggle-select-btn' + select_id).find('span').text(allVisibleSelected ? 'Deselect All' : 'Select All');
}

function updateSelectCount() {
  // optional: add a small counter display if you want
  // $('#select-count' + select_id).text("Selected: " + selectedRows.size);
  updateToggleButton();
}

// Replace your existing Select/Deselect all handler with this:
$('#toggle-select-btn' + select_id).on('click', function (e) {
  e.preventDefault();
  var $btn = $(this);
  var visibleApi = table.rows({ search: 'applied' }); // change to table.rows() to operate on ALL rows
  var visibleCount = visibleApi.count();

  if (visibleCount === 0) return;

  // Check whether all visible rows are currently selected
  var allVisibleSelected = true;
  visibleApi.every(function() {
    var data = this.data();
    var rowId = featureIdColumnIndex >= 0 ? data[featureIdColumnIndex] : this.index();
    if (!selectedRows.has(rowId)) { allVisibleSelected = false; return false; }
  });

  if (allVisibleSelected) {
    // Deselect visible rows
    visibleApi.every(function() {
      var node = this.node();
      var data = this.data();
      var rowId = featureIdColumnIndex >= 0 ? data[featureIdColumnIndex] : this.index();
      selectedRows.delete(rowId);
      $(node).find('input.row-select').prop('checked', false);
      $(node).removeClass('selected');
    });
    $btn.find('span').text('Select All');
  } else {
    // Select visible rows
    visibleApi.every(function() {
      var node = this.node();
      var data = this.data();
      var rowId = featureIdColumnIndex >= 0 ? data[featureIdColumnIndex] : this.index();
      selectedRows.add(rowId);
      $(node).find('input.row-select').prop('checked', true);
      $(node).addClass('selected');
    });
    $btn.find('span').text('Deselect All');
  }

  // keep table UI in sync — avoid full redraw if not necessary
  // draw(false) will trigger drawCallback -> restoreSelections(), which is fine, but we've already updated DOM, so call draw(false) to keep other UI consistent:
  table.draw(false);
  updateSelectCount();
});

// Update the single-checkbox handler so it updates the toggle button text too:
$(table_id).on('change', '.row-select', function () {
  var $row = $(this).closest('tr');
  var data = table.row($row).data();
  var rowId = featureIdColumnIndex >= 0 ? data[featureIdColumnIndex] : table.row($row).index();

  if (this.checked) {
    selectedRows.add(rowId);
    $row.addClass('selected');
  } else {
    selectedRows.delete(rowId);
    $row.removeClass('selected');
  }

  updateSelectCount();
});


// --- Export helpers ---
function exportSelectedRows(idx, data, node) {
  // Return only rows where the checkbox is checked
  return $(node).find('input.row-select').is(':checked');
}

function exportAction(action) {
  return function (e, dt, button, config) {
    var selectedIndexes = [];

    dt.rows().every(function () {
      var node = this.node();
      if ($(node).find('input.row-select').is(':checked')) {
        selectedIndexes.push(this.index());
      }
    });

    if (selectedIndexes.length > 0) {
      config.rows = selectedIndexes;  // export only selected rows
    } else {
      alert("Please select at least one row to export.");
      return;
    }

    $.fn.dataTable.ext.buttons[action].action.call(
      this,
      e,
      dt,
      button,
      config
    );
  };
}


function fastaExportAction(e, dt, button, config) {
  // Get DataTable API instance from dt param
  var table = dt;

  // --- Detect Feature ID column index on the fly ---
  var featureIdColumnIndex = -1;
  table.columns().every(function (idx) {
    var hdr = $(table.column(idx).header()).text().trim();
    if (hdr.toLowerCase() === 'feature id') {
      featureIdColumnIndex = idx;
    }
  });
  if (featureIdColumnIndex === -1) {
    alert("Feature ID column not found.");
    return;
  }

  // --- Collect selected rows ---
  var selectedApi = table.rows(function (idx, data, node) {
    return $(node).find('input.row-select').is(':checked');
  });
  var selectedData = selectedApi.data();

  if (selectedData.length === 0) {
    alert("Please select at least one row.");
    return;
  }

  var featureIds = [];

  for (var i = 0; i < selectedData.length; i++) {
    var row = selectedData[i];

    // First try to get directly from DataTables data
    var featureIdCell = row[featureIdColumnIndex];

    // Fallback: get directly from DOM if undefined
    if (typeof featureIdCell === 'undefined') {
      var node = selectedApi.nodes()[i];
      featureIdCell = $(node).find('td').eq(featureIdColumnIndex).html();
    }

    // Strip HTML and get plain text
    var div = document.createElement('div');
    div.innerHTML = featureIdCell || '';
    var cleanFeatureId = (div.textContent || div.innerText || '').trim();

    if (cleanFeatureId !== '') {
      featureIds.push(cleanFeatureId);
    }
  }

  if (featureIds.length === 0) {
    alert("No valid Feature IDs found in selected rows.");
    return;
  }

  // --- Open FASTA download page ---
//  var url = "../fasta_download.php?uniquenames=" + encodeURIComponent(featureIds.join(","));
//  window.open(url, "_blank");
	  // Create and submit a hidden form with POST
  var form = document.createElement("form");
  form.method = "POST";
  form.action = "../fasta_download.php";
  form.target = "_blank"; // open in new tab

  var input = document.createElement("input");
  input.type = "hidden";
  input.name = "uniquenames";
  // Remove duplicates
  let uniqueFeatureIds = [...new Set(featureIds)];

  // Join into comma-separated string
  input.value = uniqueFeatureIds.join(",");
  //input.value = featureIds.join(",");
  form.appendChild(input);

  document.body.appendChild(form);
  form.submit();
  document.body.removeChild(form);
}



  }
}

