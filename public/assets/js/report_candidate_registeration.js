$(document).ready(function()
{
    var series_code;
    var centre_id;
    //change in paper series dropdown list will trigger this function and generate dropdown for centre id
    $(document).on('change', '#series_code_select', function()
    {
        series_code = $(this).val();
        if(series_code != "") 
        {
            $.ajax({
                url:"report_db_can_reg_centres.php",
                type:'POST',
                data:{series_code:series_code},
                success:function(data){
                   if(data != ""){
                        $("#centre_id_select").attr('disabled', false).html(data);
                        //$("#centre_id_select").html(response);
                   }
                }
            });
        }
        else {
            $("#centre_id_select").attr('disabled','disabled').html("");
        }
    });
    $(document).on('change','#centre_id_select', function(){
         centre_id = $(this).val();
         if (centre_id != "")
         {
            $("#generateRep").attr('disabled',false);
         }
         else
         {
            $("#generateRep").attr('disabled','disabled');
         }
        
    });

    $(document).on('click', "#generateRep", function()
    {
         series_code_select = document.getElementById("series_code_select").value;
         centre_id_select = document.getElementById("centre_id_select").value;

        $.ajax({
            url:report_can_reg.php,
            type:'POST',
            data:{series_code_select:series_code_select, centre_id_select:centre_id_select},
            success:function(data){
            }
        });
    }
    );
});