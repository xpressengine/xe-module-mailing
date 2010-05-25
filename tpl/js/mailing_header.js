function doBoardToggleMailing(module_srl, obj) {
    var params = new Array();
    params['module_srl'] = module_srl;
    exec_xml('mailing','procMailingToggleList',params, function() { completeBoardToggleMailing(obj) } );
}

function completeBoardToggleMailing(obj) {
    location.reload();
}

jQuery(function($){
	$('.mailingInfo .desc').hide();
	$('.mailingInfo .mBtn>button').click(function(){
		if(!$(this).parent('.mBtn').next('.desc').hasClass('open')){
			$(this).parent('.mBtn').next('.desc').addClass('open').slideDown(200);
		} else {
			$(this).parent('.mBtn').next('.desc').removeClass('open').slideUp(200);
		}
	});
	$('.mailingInfo .desc>.close').click(function(){
		$(this).parent('.desc').removeClass('open').slideUp(200);
	});
});