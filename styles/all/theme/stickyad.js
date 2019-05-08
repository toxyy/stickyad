head.ready(function () {
    let collapsed = false;
    $('a.toxyyshowhidelink').click(function(e) {
        if($(e.target).closest('.childDiv').length==0 ) {
            if(collapsed) {
                $('a.toxyyadcollapsed').removeClass('toxyyadcollapsed');
                $('a.toxyyadflipbutton').removeClass('toxyyadflipbutton');
                $('div.toxyyzeroheight').removeClass('toxyyzeroheight');
                $('a.toxyystickyurl').addClass('toxyyadshow');
                collapsed = false;
            } else {
                $('a.toxyyadshow').removeClass('toxyyadshow');
                $('a.toxyystickyurl').addClass('toxyyadcollapsed');
                $('a.toxyyshowhidelink').addClass('toxyyadflipbutton');
                $('div#toxyystickyadbox').addClass('toxyyzeroheight');
                collapsed = true;
            }
        }
    });
});
