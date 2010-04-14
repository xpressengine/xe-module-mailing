function doBoardToggleMailing(module_srl, obj) {
    var params = new Array();
    params['module_srl'] = module_srl;
    exec_xml('mailing','procMailingToggleList',params, function() { completeBoardToggleMailing(obj) } );
}

function completeBoardToggleMailing(obj) {
    var o = jQuery(obj);
    if(o.hasClass('join')) {
        o.removeClass('join');
        o.addClass('leave');
    } else {
        o.removeClass('leave');
        o.addClass('join');
    }
    location.reload();
}
