function doToggleMailing(module_srl, obj) {
    var params = new Array();
    params['module_srl'] = module_srl;
    exec_xml('mailing','procMailingToggleList',params, function() { completeToggleMailing(obj) } );
}

function completeToggleMailing(obj) {
    var o = jQuery(obj);
    if(o.hasClass('join')) {
        o.removeClass('join');
        o.addClass('leave');
    } else {
        o.removeClass('leave');
        o.addClass('join');
    }
}

function doToggleMailingTree(obj) {
    var o = jQuery(obj);
    var n = o.parent().attr('id').replace(/^tree_/,'');
    if(o.parent().hasClass('nav_tree_off')) {
        o.parent().removeClass('nav_tree_off');
        o.parent().addClass('nav_tree_on');
        o.html('-');
        doAddFolder(n);
    } else {
        o.parent().removeClass('nav_tree_on');
        o.parent().addClass('nav_tree_off');
        o.html('+');
        doRemoveFolder(n);
    }
}

function doAddFolder(n) {
    var c = doGetFolder();
    if(c) {
        var ca = c.split(',');
        for(var i=0;i<ca.length; i++) {
            if(ca[i]==n) return;
        }
    }
    c = c+','+n;
    doWriteFolder(c);
}

function doRemoveFolder(n) {
    var c = doGetFolder(), v = n+',';
    if(!c) return;
    var ca = c.split(',');
    var nca = new Array();
    for(var i=0;i<ca.length; i++) {
        if(ca[i]==n) continue;
        nca[nca.length] = ca[i];
    }
    c = nca.join(',');
    doWriteFolder(c);
}

function doWriteFolder(v) {
    var n = new Date();
    n.setTime(n.getTime() + (30 * 24 * 60 * 60 * 1000));

    document.cookie = 'mN='+escape(v)+'; expires='+n.toGMTString() + '; path=/';
}

function doGetFolder() {
    if(document.cookie.length<1) return;
    var value='',search = 'mN'+"=";
    var offset = document.cookie.indexOf(search);
    if (offset != -1) {
      offset += search.length;
      var end = document.cookie.indexOf(";", offset);
      if (end == -1) end = document.cookie.length;
      value = unescape(document.cookie.substring(offset, end));
    }
    return value;

}

function doCheckFolder() {
    var c = doGetFolder();
    if(!c) return;
    var n = c.split(',');
    for(var i=0;i<n.length;i++) {
        var o = jQuery('#tree_'+n[i]);
        if(!o) continue;
        o.removeClass('nav_tree_off');
        o.addClass('nav_tree_on');
    }
}

function doShowSummary(e) {
    var o = jQuery('#summaryArea');
    if(!o.length) o = jQuery('<div>').attr('id','summaryArea').attr('class','contentSummary').css('width','400px').appendTo('body');
    var html = jQuery(e.target).parent().find('.contentSummary').html(); 
    if(!html) return;
    o.html(html).css('left', e.pageX+'px').css('top',(e.pageY+10)+'px').css('display','block');
}

function doHideSummary(obj) {
    var o = jQuery('#summaryArea');
    if(!o) return;
    o.css('display','none');
}
