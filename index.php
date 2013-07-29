<?php 
/* 
Plugin Name: Add to favorite for Buddypress
Description: Add/Remove posts to/from users favorite list
Version: 0.3
Author: E Media Identity
Author URI: http://emediaidentity.com/
Network: true
*/

add_action( 'init', 'atffb_init_plugin' );

function atffb_init_plugin(){
	$instance = AddToFavBP::get_instance();
}

class AddToFavBP {

	// static function only use static function hence this var is used in  get_instance() method.
	private static $instance;

	function __construct() {
		include_once( "functions.php" );
		/*=====================================
		make sure all posts have their post meta EMI_FAVORITE_COUNT (default: 0)
		so that none are left on 'most favorited posts' section
		(if the post meta doesn't exist, order by post meta wont include those posts)
		====================================*/
		add_action('save_post', array( $this, 'set_favorite_count_default' ) );
		add_action( 'admin_menu', array( $this, 'SettingsFavMenu' ) );

		if( is_user_logged_in() ){
			add_action( 'wp_enqueue_scripts',  array( $this, 'enqueue_scripts' ) );
			$this->setupAjax();
		}
	}
	public static function get_instance(){
        if(!isset(self::$instance)){
            self::$instance=new self();
        }
        return self::$instance;
    }
	function SettingsFavMenu(){
		add_options_page( 'Favorites Configuration', 'Favorite config', 'manage_options', 'emi_favorites_config', array( $this, 'FavoritesSettingsMenu_page' ) );
	}

	/*
	 * This function is used to Display Dashboard Setting for Favorite link setting.
	*/
	function FavoritesSettingsMenu_page(){

		if( isset($_POST['savefavlinksetting']) ){
			$selectedsetting = $_POST['setting'];
			update_option('emi_fav_link_setting',$selectedsetting);

			echo "setting saved";
		}
		if( isset($_POST['savefavtextsetting']) ){
			$usertext = array();
			$addfavtext = $_POST['addfavtext'];
			$removefavtext = $_POST['removefavtext'];
			if( $addfavtext != "" && $removefavtext != "" ){
				$usertext[] = $addfavtext;
				$usertext[] = $removefavtext;
				update_option('emi_fav_link_text',$usertext);
				echo "setting saved";
			}
		}
		
		$userfavsetting = get_option('emi_fav_link_setting');
		$userfavlinktext = get_option('emi_fav_link_text');
		if( is_array($userfavsetting) ){
			$check = true;
		}
		else
		{
			$check = false;	
		}
		$args=array(
		  'public'   => true,
		  '_builtin' => false
		); 
		$output = 'names'; // names or objects, note names is the default
		$operator = 'and'; // 'and' or 'or'
		$data = get_post_types($args,$output,$operator); 
		
		?>
		<form action="" method="post">
			<h2> Favorites Setting Options </h2>
			<div class="favlinksetting">
				<ul>
					<li>
						<label><input type="checkbox" <?php if($check){ echo in_array('pages', $userfavsetting)?'checked':''; } ?> name="setting[]" value="pages" /> Display Favorite Link for all pages</label>
					</li>
					<li>
						<label><input type="checkbox" <?php if($check){ echo in_array('posts', $userfavsetting)?'checked':''; } ?> name="setting[]" value="posts" /> Display Favorite Link for Blog posts</label>
					</li>
					<?php
					foreach ($data as $cptname) {
						if($check){ 
							$ischecked = in_array($cptname, $userfavsetting)?'checked':'';
						}else { $ischecked = ''; }
						echo '<li><label>';
							echo "<input type='checkbox' $ischecked name='setting[]' value='$cptname' />";
							echo " Display Favorite Link for all $cptname post";
						echo '</label></li>';
					}
					?>
					<li>
						<div class="favsettingbtn">
							<input type="submit" name="savefavlinksetting" value="Save Settings" class="button button-primary button-large" />
						</div>
					</li>
				</ul>
			</div>
			<div class="linktext">
				<ul>
					<li>
						<label>Text for add to favorite link :</label>
						<input type="text" name="addfavtext" value="<?php echo $userfavlinktext[0]; ?>" placeholder="e.g: Add to Fav" autocomplete="off"/>
					</li>
					<li>
						<label>Text for remove from favorite link :</label>
						<input type="text" name="removefavtext" value="<?php echo $userfavlinktext[1]; ?>" placeholder="e.g: Remove from Fav" autocomplete="off" />
					</li>
					<li>
						<div class="favtextsettingbtn">
							<input type="submit" name="savefavtextsetting" value="Save Changes" class="button button-primary button-large" />
						</div>
					</li>
				</ul>
			</div>
		</form>
		<?php
	}
	
	function enqueue_scripts(){
		wp_enqueue_script(
			"atffb",
			path_join( WP_PLUGIN_URL, basename( dirname( __FILE__ ) )."/atffb.js" ),
			array( 'jquery' )
		);
	}

	function setupAjax(){
		add_action( 'wp_ajax_atffb_set_favorite', array( $this, 'set_favorite_item_ajax' ) );
	}
	
	function set_favorite_count_default($post_id) {
		//we are concerned only with published posts
		if( 'publish' == get_post_status( $post_id ) && 'post' == get_post_type( $post_id ) ){
			$favorite_count = get_post_meta($post_id, 'EMI_FAVORITE_COUNT', true);
			if(!$favorite_count){
				// unhook this function so it doesn't loop infinitely
				remove_action('save_post', array( $this, 'set_favorite_count_default' ) );
				
				update_post_meta($post_id, 'EMI_FAVORITE_COUNT', 0);
				
				// re-hook this function
				add_action('save_post', array( $this, 'set_favorite_count_default' ) );
			}
		}
	}

	/*
	 * This function is called when ajax request is made in javascript for adding post to favorites.
	*/
	function set_favorite_item_ajax(){
		$object_type = $_POST['object_type'];
		$object_parent = $_POST['object_parent'];
		$object_id = $_POST['object_id'];
		//echo "$object_type : $object_parent : $object_id";
		$retval = $this->set_favorite_item( $object_type,$object_parent,$object_id );	
		echo json_encode($retval);
		exit;
	}

	/*
	 * add the look/upload/entry (or whatever you call it) to the list of favorite list for the user
	 * removes from the favorite list if already there
	*/
	function set_favorite_item( $object_type_arg,$object_parent_arg,$object_id_arg ){
		//return;
		$retval = array(
			'status' 		=> 'error',
			'message'		=> '',
			'addedclass' 	=> 'addedd_to_favorites',
			'state'			=> 'not-added',
			'count'			=> 0
		);
		
		$user_id = bp_loggedin_user_id();
		$object_type = $object_type_arg;
		$object_parent = $object_parent_arg;
		$object_id = $object_id_arg;	

		//update_option( 'emi_debug', 'User Id :'.$user_id.'|| Type : '.$object_type.' || Parent : '.$object_parent.' ||object Id :  '.$object_id );
		$objectids_arr = get_users_favorite_items_ids( $object_type, $object_parent, $user_id );

		$new_user_meta = array();
		if( $objectids_arr && !empty($objectids_arr) ){
			foreach($objectids_arr as $id){
				if($object_id == $id){
					//dont add if its already there
					$item_already_added = true;
				}
				else{
					$new_user_meta[] = $id;

				}
			}
		} else {
			$item_already_added = false;
		}
	
		$item_favorites_count = update_item_favorites_count( $object_type, $object_parent, $object_id, $item_already_added );

		
		$retval['count'] = $item_favorites_count;
		
		if( !$item_already_added ){
			$new_user_meta[] = $object_id;
			//remove the posts which have been deleted
			//$new_user_meta = $this->remove_deleted_posts_id( $new_user_meta );
			update_user_meta( $user_id, get_user_meta_fav_key( $object_type, $object_parent ) , $new_user_meta );
			
			$retval['status'] = 'success';
			$retval['message'] = __('Successfuly added to your favorites', 'buddypress');
			$retval['state'] = 'added';
			//record an activity
			$act_args = array(
					"object_id"		=> $object_id
			);
			$activity_id = $this->add_favorite_activity( $act_args );
		}
		else{

			update_user_meta( $user_id, get_user_meta_fav_key( $object_type, $object_parent ) , $new_user_meta );
			
			$retval['status'] = 'success';
			$retval['message'] = __('Successfuly removed from your favorites', 'buddypress');

		}
		return $retval;
	}

	/* records an activity when user adds any item to his/her favorite
	 * @param mixed string|array $args
	 * @return void
	*/
	function add_favorite_activity( $args ){
		$defaults = array(
			'object_id'		=> false
		);
		$r = wp_parse_args( $args, $defaults );
		extract( $r );
		
		$action = bp_core_get_userlink( bp_loggedin_user_id() ) . " liked <a href='". get_permalink( $object_id ) ."'>" . get_the_title( $item_id ) . "</a>";
		//$action = "liked a post";
		$type = "item_favorited";
		
		$params = array(
			'content'            => $action, // The activity action - e.g. "Jon Doe posted an update"
			//'type'              => $type, // The activity type e.g. activity_update, profile_updated
			'object_id'           => $object_id // Optional: The ID of the specific item being recorded, e.g. a blog_id
		);
		
		return bp_activity_add( array(
				'object_id'=> $object_id,
				'user_id' => bp_loggedin_user_id(),
				'action' => $action,
				'component' => 'post',
				'type' => 'post_favorited'
				) 
			);
	}

	/* removes the id of posts which have been delted
	 * @param array $posts_id. the array of post ids to check
	 * @return array. id of valid posts
	**/
	function remove_deleted_posts_id( $objects_id ){
		if( !empty( $objects_id ) ):
			$filtered_post_ids = array();
			$found_post_ids = array();
			
			$fil_query = new WP_Query( array( "post__in" => $objects_id ) );
			if( $fil_query->have_posts() ): while( $fil_query->have_posts() ): $fil_query->the_post();
				$found_post_ids[] = get_the_ID();
			endwhile; endif;
			wp_reset_query();
			
			//the extra loop is to preserve the order of ids in the original list
			foreach( $posts_id as $id ):
				if( in_array( $id, $found_post_ids ) && !in_array( $id, $filtered_post_ids ) ){
					//second condition in the 'if' above is to prevent duplicate entries
					$filtered_post_ids[] = $id;
				}
			endforeach;
			
			return $filtered_post_ids;
		endif;
		return false;
	}
}
