$(document).ready(function(){
  $("#tablefilterinput").on("keyup", function() {
    var value = $(this).val().toLowerCase();
    $("#dashgridtable tr").filter(function() {
      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -2)
    });
  });
});