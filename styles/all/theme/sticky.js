head.ready(function () {
    let collapsed = false;
    $('a.toxyyshowhidelink').click(function(e) {
        if($(e.target).closest('.childDiv').length==0 ) {
            if(collapsed) {
                $('.toxyyadcollapsed').removeClass('toxyyadcollapsed');
                $('.toxyyadflipbutton').removeClass('toxyyadflipbutton');
                $('.toxyyzeroheight').removeClass('toxyyzeroheight');
                $('.toxyystickyurl').addClass('toxyyadshow');
                collapsed = false;
            } else {
                $('.toxyyadshow').removeClass('toxyyadshow');
                $('.toxyystickyurl').addClass('toxyyadcollapsed');
                $('.toxyyshowhidelink').addClass('toxyyadflipbutton');
                $('#toxyystickyadbox').addClass('toxyyzeroheight');
                collapsed = true;
            }
        }
    });
});
