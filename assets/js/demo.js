function ajaxErrorHandler(e) {
    console.log(e);
}

$(document).ready(function() {
    $('form button').click(function(){
        $.ajax({
            type: 'POST',
            async: true,
            url: 'rainer.php',
            data: $('form').serialize(),
            dataType: 'json',
            error: ajaxErrorHandler,
            success: function(data){
                var result = $('#result').find('pre code');
                result.text(JSON.stringify(data,null,3));
            }
        });
        return false;
    });
});
