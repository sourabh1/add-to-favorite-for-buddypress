var jq=jQuery.noConflict();

jq(document).ready(function(){
	jq(".js_add_to_favorites").click(function(){
		toggle_into_favorite_recipes(jq(this));
		return false;
	});
});

/**************************************
add/remove to favorite recipe - ajax calls
**************************************/
function toggle_into_favorite_recipes(link_object){
	//make use of buddypress default bp_ajax_request variable to prevent multiple simultaneous ajax requests to server */
	if( bp_ajax_request ){
		alert( 'Please wait another request is processing..' );
		return false;
	}

	jq(link_object).attr('disabled','disabled').removeClass('addedd_to_favorites').addClass("doingajax");

	var data = {
		action: 'atffb_set_favorite',
		object_type: jq(link_object).attr('data-object_type'),
		object_parent: jq(link_object).attr('data-object_parent'),
		object_id: jq(link_object).attr('data-objectid')
	};
	//alert('Typ eof obj is : ' + data.object_type+'\nParent of this obj type : '+data.object_parent+'\nId of this boject type : '+data.object_id);

	bp_ajax_request = true;
	// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
	jq.ajax({
		type: "POST",
		url: ajaxurl,
		data: data,
		success: function(response) {

			
				after_set_favorite_item(response, link_object);
				bp_ajax_request = null;//reset it
				/*var result = jQuery.parseJSON(response);*/
				/*alert(result);*/
			
		}
	});
}

function after_set_favorite_item(response, link_object){
	alert(response);

	var result = jQuery.parseJSON(response);
	//alert(result.status + " : message : " + result.message + " : anchor text : " + result.anchortext);
	jq(link_object).removeAttr('disabled').removeClass("doingajax");

	if(result.status=='success'){
		//if user has just removed from favorites or added to
		if(result.state=="added"){
			jq(link_object).addClass(result.addedclass);
			jq(link_object).attr('title', jq(link_object).attr('data-removetitle'));
			//alert( result.message );
		}
		else{
			jq(link_object).removeClass(result.addedclass);
			jq(link_object).attr('title', jq(link_object).attr('data-addtitle'));
		}
	}
	else{
		/*temporarily display the error message (as anchors text) and then revert back to original text*/
	}
}