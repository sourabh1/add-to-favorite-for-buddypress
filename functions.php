<?php 
/* library functions for plugin Add-to-favorites-for-Buddypress */


/*
 * the functiont return the 'meta_key' name accordint to the arguments passed
 * the key is the name agains what the favorite list will be saved in user meta
 * e.g: the key name for favorites list of custom post type called recipes should be 'EMIFAVS_CPT_RECIPES'
 *		the key name for favorites list of taxonomy called recipe categories should be 'EMIFAVS_TAX_RECIPES-CATEGORIES'
 *
 * @param string $object_type : post|page|taxonomy
 * @param string $object_parent : name of the cpt|name of the taxonomy | ""
 *
 * @return mixed string:meta key name|false (if any error)
*/
function get_user_meta_fav_key( $object_type, $object_parent ){
	$meta_key_name = "EMIFAVS_";//the default prefix

	//posts
	switch ($object_type) {
		case 'post':
			//it could be either normal blog posts or cutom post types, so lets check the object_parent(name of cpt)
			if( $object_parent && !empty($object_parent) ){
				//the calling functions has to make sure that there are no blank spaces in cpt names
				if( $object_parent=="post" ){
					//normal blog posts
					$meta_key_name .= "POSTS";
				}
				else{
					$meta_key_name .= "CPT_". $object_parent;
				}
			}
			else{
				$meta_key_name .= "POSTS";
			}
			break;
		case 'page':
			$meta_key_name .= "PAGES";
			break;
		case 'taxonomy':
			$meta_key_name .= "TAX_" . $object_parent;
			break;
		default:
			$meta_key_name = "it is null";
			break;
	}

	return $meta_key_name;
}

/*
 * use this function to get ids of all items a user has added to his/her favorites
 * can be used while querying user's favorites posts
 *
 * @param string $object_type : post|page|taxonomy
 * @param string $object_parent : name of the cpt|name of the taxonomy | ""
 * @param int $object_id : id of the post|cpt|page|term
 * @param int $user_id : default:0 (logged in user's id)
 *
 * @return mixed : array (if found)|false if not found
*/
function get_users_favorite_items_ids( $object_type, $object_parent, $user_id=false ){
	
	if( !$user_id )
	$user_id = bp_loggedin_user_id();
	
	
	$postids_arr = get_user_meta( $user_id, get_user_meta_fav_key( $object_type, $object_parent ), true);
	
	return apply_filters( 'get_users_favorite_items_ids', $postids_arr, $user_id );
}

if( !function_exists('add_to_fav_link') ):
/*
 * The template tag to generate Add-to-fav/remove-from-fav link
 *
 * use the filter to override if required
*/
function add_to_fav_link( $args="" ){

	//dont do anything if user is not logged in
	if( !is_user_logged_in() )
		return;

	

	$userfavlinktext = get_option('emi_fav_link_text');
	$defaults = array(
		"object_type"	=> "post", //post|page|taxonomy
		"object_parent"	=> "",//name of cpt|name of taxonomy
		"object_id"		=> get_the_ID(),//post_id|page_id|termId
		"user_id"		=> bp_loggedin_user_id(),
		"type"			=> "anchor", //anchor|button
		"class"			=> "", //additional css class to add
		"text_add"		=> "$userfavlinktext[0]",
		"text_remove"	=> "$userfavlinktext[1]",
		"echo"			=> true //pass false to return the html instead of echoing
	);
	$args = wp_parse_args( $args, $defaults );
	extract( $args, EXTR_SKIP );

	// this code is executed when javascript is turned off.
	if( isset($_GET['object_type']) && isset($_GET['object_parent']) && isset($_GET['object_id']) ){
		
		$instance = AddToFavBP::get_instance();
		$object_type_url = $_GET['object_type'];
		$object_parent_url = $_GET['object_parent'];
		$object_id_url = $_GET['object_id'];
		if( $object_id_url == $object_id ){
			$retval = $instance->set_favorite_item($object_type_url,$object_parent_url,$object_id_url);
		}
		//echo $retval['message'].'<br>';
		global $wp;
		$current_url = add_query_arg( $wp->query_string, '', home_url( $wp->request ) );
		$path = explode('?', $current_url);
		wp_redirect( $path[0] );
	}
	//for this website we want the text for 'remove from fav' and 'add to fav' as same, so lets simply override the value of text_remove with that of text_add
	//$text_remove = $text_add;
	
	$anchor_text = "";

	$flag = is_item_in_users_favorite( $object_type, $object_parent, $object_id, $user_id );

	if( !$flag ){
		//item not in user's favorite
		if( $text_add )
			$anchor_text = $text_add;
	} else {
		if( $text_remove )
			$anchor_text = $text_remove;
	}

	if( !$anchor_text )
		$anchor_text = get_add_to_favorite_anchor_text( $flag );

	$data_add_title = ( $text_add ? $text_add : get_add_to_favorite_anchor_text(false) );

	//for this website we want the text for 'remove from fav' and 'add to fav' as same, so lets have a different version of the original code(the line below it)
	$data_remove_title = ( $text_remove ? $text_remove : get_add_to_favorite_anchor_text(true) );
	//$data_remove_title = $data_add_title;

	$hrefAttr = "";
	if( $type != "button" ){
		$hrefAttr = add_to_favorite_link_link( $object_type,$object_parent,$object_id, get_permalink( $object_id ) );
	}

	$class .= " " . get_add_to_favorites_anchor_class( $flag );

	$html = "";
	if( $type != "button" ){
		$html .= "<a $hrefAttr class='$class' id='atffb-$object_id' data-object_type='$object_type' data-object_parent='$object_parent' data-objectid='$object_id' data-addtitle='$data_add_title' data-removetitle='$data_remove_title'>$anchor_text</a>";
	} else {
		$html .= "<button class='$class' id='atffb-$object_id' data-objectid='$post_id' value='$anchor_text' data-addtitle='$data_add_title' data-removetitle='$data_remove_title' />";
	}
	$html = apply_filters( 'add-to-fav-link', $html, $flag, $type, $post_id );
	if( $echo )
		echo $html;
	else
		return $html;
}
endif;
add_shortcode('favorite-button-link','add_to_fav_link');


/* is_item_in_users_favorite
 * check if the given item is in (given) user's favorite list
 * @param string $object_type : post|page|taxonomy
 * @param string $object_parent : name of the cpt|name of the taxonomy | ""
 * @param int $object_id : id of the post|cpt|page|term
 * @param int $user_id : default:0 (logged in user's id)
 * return boolean true/false
 */
function is_item_in_users_favorite( $object_type, $object_parent, $object_id, $user_id=0){
	if(!$user_id || $user_id==0){
		$user_id = bp_loggedin_user_id();
	}

	$item_already_added = false;

	$favids_arr = get_users_favorite_items_ids( $object_type, $object_parent, $user_id );

	if( !empty( $favids_arr ) && in_array( $object_id, $favids_arr ) ){
		$item_already_added = true;
	}
	return $item_already_added;
}


/* 
 * return the text for the link to 'add to my favorites' anchor.
 * @param $flag 
			true: returns 'Remove from my favorites': false: returns 'Add to my favorites' 
			default false
 */
function get_add_to_favorite_anchor_text($flag=false){
	/* will later fetch this from settings(options) instead of hardcoded values */
	if($flag==true){
		return __('- Remove from my favorites', 'buddypress');
	}
	else{
		return __('+ Add to my favorites', 'buddypress');
	}
}


function get_add_to_favorites_anchor_class($flag){
	/* IMP : js_add_to_favorites must be added, the javascript event is binded based on that */
	if($flag){
		return "addedd_to_favorites js_add_to_favorites";
	} else {
		return "js_add_to_favorites";
	}
}

/* add_to_favorite_link_link
 * echoes the value for href attribute for the add-to-favorite anchor element
 * 	if the user is logged in  - ''
 * 	else login link with redirect to the concerned page
 * @param $item_permalink string the url of the item being viewed
 */
function add_to_favorite_link_link($object_type,$object_parent,$object_id,$item_permalink){
	global $bp;
	if($bp->loggedin_user->id && $bp->loggedin_user->id!=0){
		return " href=?object_type=$object_type&object_parent=$object_parent&object_id=$object_id";
	}
	else{
		return " href='" . wp_login_url( $item_permalink )."'";
	}
	
}

/*
 * no of items user has added to his/her favorites 
 * @uses get_users_favorite_items_ids
 *
 * @param int user_id : user whose favorite counts is to be retrieved
 * @return int count
*/
function get_users_favorite_items_count( $user_id=false ){
	$fav_list = get_users_favorite_items_ids( $user_id );

	if( $fav_list ){
		return count( $fav_list );
	}

	return 0;
}

/* see  get_item_favorites_count() */
function item_favorites_count( $item_id=false ){
	echo get_item_favorites_count( $item_id );
}

/* 
 * how many users have added the given post to their favorites list
 * @param int post_id : default global post id
 * @return int count
*/
function get_item_favorites_count( $object_type, $object_parent, $object_id=false ){
	$count = 0;

	if( !$object_id )
		$object_id = get_the_ID();

	if( $object_type == 'post' || $object_type == 'page' ){
		$count = (int)get_post_meta($object_id, 'EMI_FAVORITE_COUNT', true);
	}
	else{
		$taxonomydata = get_option( get_user_meta_fav_key( $object_type, $object_parent) );
		//the sample data for $taxonomydata : array( '11'=> '1' , 'Breads' => '2' )
		foreach ($taxonomydata as $key => $value) {
			if($key == $object_id){
				$count = $value;
				break;
			}
		}
	}

	return apply_filters( 'get_item_favorites_count', $count, $object_type, $object_parent, $object_id );
}

/* update_item_favorites_count - how many times an item has been favorited
 * updates the favorites count for given item
 *
 * @param string $object_type : post|page|taxonomy
 * @param string $object_parent : name of the cpt|name of the taxonomy | ""
 * @param int $object_id : id of the post|cpt|page|term
 * @param int $user_id : default:0 (logged in user's id)
 * @param $decrement bool whether to decrease the count
		true: the favorite count is decreased by one
		false: the favorite count is increased by one
 * return int updated favorite count
 */
function update_item_favorites_count($object_type, $object_parent, $object_id, $decrement) {
	if( $object_type == 'post' || $object_type == 'page' ){
		$previous_count = (int)get_item_favorites_count( $object_type, $object_parent, $object_id );
		$new_count = ( $decrement ? $previous_count-1 : $previous_count+1 );
		update_post_meta( $object_id, 'EMI_FAVORITE_COUNT', $new_count );
	}
	else{
		/*
		 * structure of an array in wp_option will be:
		 *		'EMIFAVS_recipe_categories' => array( '11'=> '1' , '16' => '2' )
		 *		'EMIFAVS_recipe' => array( 'pizza' => '3' , 'Breads' => '1' )
		 *		'EMIFAVS_ingredients' => array( 'Capsicum' => '5' , 'Breads' => '2' )
		*/
		$user_meta_fav_key = get_user_meta_fav_key($object_type, $object_parent);
		$taxonomydata = get_option( $user_meta_fav_key );
		
		if( $taxonomydata && !empty( $taxonomydata ) ){
			$previous_count = $taxonomydata[$object_id];
			$new_count = ( $decrement ? $previous_count-1 : $previous_count+1 );
			$taxonomydata[$object_id] = $new_count;
		}
		else{
			//the key doesn't exist in db, no user had added the taxonomy to their favorites
			//so create a new key and update to db
			$taxonomydata = array( $object_id=>1 );
		}

		update_option( $user_meta_fav_key, $taxonomydata );
	}
	return $new_count;
}

function add_fav_link_sections(){
	
	global $post;
	$usersetting = get_option('emi_fav_link_setting');
	if( is_array($usersetting) ){
		$userfavlinktext = get_option('emi_fav_link_text');
		foreach ($usersetting as $value) {
			if( $post->post_type == 'post' and is_home() ){
				if( $value == 'posts' ){
					echo "<h4>";
					add_to_fav_link();
					echo "</h4>";
					break;
				}
			}
			
			if ( $post->post_type == 'recipes' and is_post_type_archive( 'recipes' ) ) {
				if( $value == 'recipes' ){
					$defaults = array(
						"object_type"	=> "post", //post|page|taxonomy
						"object_parent"	=> "recipes"//name of cpt|name of taxonomy
					);
					echo "<h4>";
					add_to_fav_link($defaults);
					echo "</h4>";
					break;
				}
			}
			if ( is_single() ) {
				if( $value == 'pages' ){
					if( is_singular('recipes') ){
						$defaults = array(
							"object_type"	=> "post", //post|page|taxonomy
							"object_parent"	=> "recipes"//name of cpt|name of taxonomy
						);
					}
					echo "<h4>";
					add_to_fav_link($defaults);
					//do_shortcode("[favorite-button-link object_type	= 'post' object_parent = 'recipes']");
					echo "</h4>";
					break;

				}
			}
		}
	}
}
add_filter('the_content','add_fav_link_sections');

