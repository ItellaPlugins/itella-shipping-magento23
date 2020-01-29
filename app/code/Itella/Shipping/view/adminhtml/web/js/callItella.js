require(['jquery'],function($){
    $('document').ready(function(){
        var Itella_url = $('#call_Itella').attr('onclick').replace("location.href = ", "");
        Itella_url = Itella_url.replace(';','');
        Itella_url = Itella_url.replace('\'','');
        $('#call_Itella').removeAttr('onclick');
        $('#call_Itella').on('click',function(e){
            e.preventDefault();
            if (confirm('Test pranesimas')) {
                location.href = Itella_url;
            }
            return false;
        });
    });
});