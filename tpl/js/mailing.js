function joinLeaveMailing(module_srl, filter)
{
    var fo_obj = jQuery("#actionForm").get(0);
    fo_obj.module_srl.value = module_srl;
    procFilter(fo_obj, filter);
}

function completeJoinLeaveList()
{
    location.href = current_url; 
}

function completeInsertMailing(ret_obj) 
{
    location.href = location.href.setQuery('module_srl',ret_obj['module_srl']);
}

function doDeleteMailing(module_srl) 
{
    var params = new Array();
    params['module_srl'] = module_srl;
    exec_xml('mailing','procMailingAdminDelete',params,completeDeleteMailing);
}

function completeDeleteMailing(ret_obj) {
    location.href = location.href.setQuery('act','dispMailingAdminIndex').setQuery('module_srl','');
}

function doConfigChange(form)
{
    var params={};
    params["maildomain"] = form.maildomain.value;
    params["display_board_header"] = form.display_board_header.checked?'Y':'';
    exec_xml('mailing', 'procMailingAdminConfig', params, filterAlertMessage ,['error', 'message'], params, form);
    return false;
}

function insertSelectedModules(id, module_srl, mid, browser_title) {
    var sel_obj = xGetElementById('_'+id);
    for(var i=0;i<sel_obj.options.length;i++) if(sel_obj.options[i].value==module_srl) return;
    var opt = new Option(browser_title+' ('+mid+')', module_srl, false, false);
    sel_obj.options[sel_obj.options.length] = opt;
    if(sel_obj.options.length>8) sel_obj.size = sel_obj.options.length;

    doSyncTargetModules(id);
}

function removeTargetModule(id) {
    var sel_obj = xGetElementById('_'+id);
    sel_obj.remove(sel_obj.selectedIndex);
    if(sel_obj.options.length) sel_obj.selectedIndex = sel_obj.options.length-1;
    doSyncTargetModules(id);
}

function doSyncTargetModules(id) {
    var selected_module_srls = new Array();
    var sel_obj = xGetElementById('_'+id);
    for(var i=0;i<sel_obj.options.length;i++) {
        selected_module_srls.push(sel_obj.options[i].value);
    }
    xGetElementById(id).value = selected_module_srls.join(',');
}
