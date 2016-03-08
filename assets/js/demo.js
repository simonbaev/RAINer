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
    $('h3 a').click(function(){
        var container = $('#qr-container').toggle();
        $(window).trigger('resize');
    });
    $(window).resize(function() {
        var container = $('#qr-container');
        var isLandscape = ($(window).width() > $(window).height());
        var minSide = isLandscape ? $(window).height() : $(window).width();
        var maxSide = isLandscape ? $(window).width() : $(window).height();
        var qrSide = minSide * 0.9;
        var minGap = (minSide - qrSide) / 2;
        var maxGap = (maxSide - qrSide)/2;
        container.find('div')
        .css({
            'width' : qrSide,
            'height' : qrSide,
            'top':  isLandscape ? minGap : maxGap,
            'left': isLandscape ? maxGap : minGap
        });
    });
    $('#qr-container').find('div').click(function(){
        $(this).parent().fadeOut();
    })
});
