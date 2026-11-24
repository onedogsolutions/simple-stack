/* Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
jQuery(document).ready(function(){
	jQuery('#sstk-skip-wizard').click(function(){
		jQuery.ajax({
			url : SSTK_Wizard.ajax_url,
			type : 'post',
			data : {
				action : 'sstk_skip_wizard_setup',
				skip_wizard : true
			},
			success : function( response ) {
				/** 
				 * We only added new option for skipping wizard
				 * On success, simply refresh the page, or redirect to about?
				*/
				window.location = SSTK_Wizard.about_page;
			}
		});
	});
});