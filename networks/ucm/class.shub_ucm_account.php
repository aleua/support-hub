<?php

class shub_ucm_account{

	public function __construct($shub_ucm_id){
		$this->load($shub_ucm_id);
	}

	private $shub_ucm_id = false; // the current user id in our system.
    private $details = array();

	/* @var $products shub_ucm_product[] */
    private $products = array();


	private $json_fields = array('ucm_data');

	private function reset(){
		$this->shub_ucm_id = false;
		$this->details = array(
			'shub_ucm_id' => false,
			'ucm_name' => false,
			'last_checked' => false,
			'ucm_data' => array(),
			'ucm_wordpress_xmlrpc' => false,
			'ucm_username' => false,
			'ucm_password' => false,
		);
	    $this->products = array();
		foreach($this->details as $field_id => $field_data){
			$this->{$field_id} = $field_data;
		}
	}

	public function create_new(){
		$this->reset();
		$this->shub_ucm_id = shub_update_insert('shub_ucm_id',false,'shub_ucm',array());
		$this->load($this->shub_ucm_id);
	}

    public function load($shub_ucm_id = false){
	    if(!$shub_ucm_id)$shub_ucm_id = $this->shub_ucm_id;
	    $this->reset();
	    $this->shub_ucm_id = (int)$shub_ucm_id;
        if($this->shub_ucm_id){
            $data = shub_get_single('shub_ucm','shub_ucm_id',$this->shub_ucm_id);
	        foreach($this->details as $key=>$val){
		        $this->details[$key] = $data && isset($data[$key]) ? $data[$key] : $val;
		        if(in_array($key,$this->json_fields)){
			        $this->details[$key] = @json_decode($this->details[$key],true);
			        if(!is_array($this->details[$key]))$this->details[$key] = array();
		        }
	        }
	        if(!is_array($this->details) || $this->details['shub_ucm_id'] != $this->shub_ucm_id){
		        $this->reset();
		        return false;
	        }
        }
        foreach($this->details as $key=>$val){
            $this->{$key} = $val;
        }
	    $this->products = array();
	    if(!$this->shub_ucm_id)return false;
	    foreach(shub_get_multiple('shub_ucm_product',array('shub_ucm_id'=>$this->shub_ucm_id),'shub_ucm_product_id') as $product){
		    $product = new shub_ucm_product($this, $product['shub_ucm_product_id']);
		    $this->products[$product->get('product_id')] = $product;
	    }
        return $this->shub_ucm_id;
    }

	public function get($field){
		return isset($this->{$field}) ? $this->{$field} : false;
	}

	public function save_data($post_data){
		if(!$this->get('shub_ucm_id')){
			$this->create_new();
		}
		if(is_array($post_data)){
			foreach($this->details as $details_key => $details_val){
				if(isset($post_data[$details_key])){
					if(($details_key == 'ucm_password') && $post_data[$details_key] == 'password')continue;
					$this->update($details_key,$post_data[$details_key]);
				}
			}
		}
		if(!isset($post_data['import_stream'])){
			$this->update('import_stream', 0);
		}
		// save the active ucm products.
		if(isset($post_data['save_ucm_products']) && $post_data['save_ucm_products'] == 'yep') {
			$currently_active_products = $this->products;
			$data = $this->get('ucm_data');
			$available_products = isset($data['products']) && is_array($data['products']) ? $data['products'] : array();
			if(isset($post_data['ucm_product']) && is_array($post_data['ucm_product'])){
				foreach($post_data['ucm_product'] as $ucm_product_id => $yesno){
					if(isset($currently_active_products[$ucm_product_id])){
						if(isset($post_data['ucm_product_product'][$ucm_product_id])){
							$currently_active_products[$ucm_product_id]->update('shub_product_id',$post_data['ucm_product_product'][$ucm_product_id]);
						}
						unset($currently_active_products[$ucm_product_id]);
					}
					if($yesno && isset($available_products[$ucm_product_id])){
						// we are adding this product to the list. check if it doesn't already exist.
						if(!isset($this->products[$ucm_product_id])){
							$product = new shub_ucm_product($this);
							$product->create_new();
							$product->update('shub_ucm_id', $this->shub_ucm_id);
							$product->update('ucm_token', 'same'); // $available_products[$ucm_product_id]['access_token']
							$product->update('product_name', $available_products[$ucm_product_id]['post_title']);
							$product->update('product_id', $ucm_product_id);
							$product->update('ucm_data', $available_products[$ucm_product_id]);
							$product->update('shub_product_id', isset($post_data['ucm_product_product'][$ucm_product_id]) ? $post_data['ucm_product_product'][$ucm_product_id] : 0);
						}
					}
				}
			}
			// remove any products that are no longer active.
			foreach($currently_active_products as $product){
				$product->delete();
			}
		}
		$this->load();
		return $this->get('shub_ucm_id');
	}
    public function update($field,$value){
	    // what fields to we allow? or not allow?
	    if(in_array($field,array('shub_ucm_id')))return;
        if($this->shub_ucm_id){
            $this->{$field} = $value;
	        if(in_array($field,$this->json_fields)){
		        $value = json_encode($value);
	        }
            shub_update_insert('shub_ucm_id',$this->shub_ucm_id,'shub_ucm',array(
	            $field => $value,
            ));
        }
    }
	public function delete(){
		if($this->shub_ucm_id) {
			// delete all the products for this twitter account.
			$products = $this->get('products');
			foreach($products as $product){
				$product->delete();
			}
			shub_delete_from_db( 'shub_ucm', 'shub_ucm_id', $this->shub_ucm_id );
		}
	}

	public function is_active(){
		// is there a 'last_checked' date?
		if(!$this->get('last_checked')){
			return false; // never checked this account, not active yet.
		}else{
			// do we have a token?
			if($this->get('ucm_token')){
				// assume we have access, we remove the token if we get a ucm failure at any point.
				return true;
			}
		}
		return false;
	}

	public function is_product_active($ucm_product_id){
		if(isset($this->products[$ucm_product_id]) && $this->products[$ucm_product_id]->get('product_id') == $ucm_product_id){
			return true;
		}else{
			return false;
		}
	}

	public function save_account_data($user_data){
		// serialise this result into ucm_data.
		if(is_array($user_data)){
			// yes, this member has some products, save these products to the account ready for selection in the settings area.
			$save_data = $this->get('ucm_data');
			if(!is_array($save_data))$save_data=array();
			$save_data = array_merge($save_data,$user_data);
			$this->update('ucm_data',$save_data);
		}
	}

	public function load_available_products(){
		// serialise this result into ucm_data.

		$api = $this->get_api();


		$api_result = $api->getProfile();
		SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO, 'ucm', 'API Result: ', $api_result);
		if($api_result && $api_result['user_id']){
			if(!isset($api_result['support_hub'])){
				echo "Error, the required PHP code has not been added to your ucm installation. Please check the Support Hub documentation.";
				return;
			}
			// this api_result will contain additional actions that we presentto the user when composing messages
            // for example ( mark thread as resolved )
			$api_result['shub_ucm_user_id'] = $this->get_api_user_to_id($api_result['user_id']);
			$this->save_account_data(array(
				'user' => $api_result,
                'reply_options' => isset($api_result['support_hub']['reply_options']) ? $api_result['support_hub']['reply_options'] : array()
			));
		}else{
			echo 'Failed to get profile from WP Api';
			return;
		}

		// get available post types, check for ucm product posts
		$api_result = $api->getPostTypes();
		//echo '<pre>';print_r($api_result);exit;
		SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO, 'ucm', 'API Result: ', $api_result);
		if($api_result && isset($api_result['product']) && isset($api_result['topic']) && isset($api_result['reply'])){
			// we have product, topic and reply post types, so I guess ucm is working.
			$api_result = $api->getPosts(array(
				'post_type' => 'product',
                'number' => 50,
			));
			//echo '<pre>';print_r($api_result);exit;
			SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO, 'ucm', 'API Result: ', $api_result);
			if(count($api_result)){
				$products = array();
				foreach($api_result as $product){
					$products[$product['post_id']] = $product;
				}
				$this->save_account_data(array(
					'products' => $products,
				));
				return true;
			}else{
				echo 'Failed to find any products, please create some.';
				return false;
			}

		}else{
			echo 'Failed to find products';
			SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_ERROR, 'ucm', 'Unable to find TOPIC and REPLY post types through the WP API, maybe ucm is not installed?');
			return false;
		}
	}

	public function run_cron( $debug = false ){


	}

	private static $api = false;
	public function get_api($use_db_code = true){
		if(!self::$api){

			//$wpLog = new Monolog\Logger('wp-xmlrpc');
			self::$api = new \HieuLe\WordpressXmlrpcClient\WordpressClient($this->get( 'ucm_wordpress_xmlrpc' ), $this->get( 'ucm_username' ), $this->get( 'ucm_password' ));
			self::$api->onSending(function($event){
			    SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO, 'ucm', 'API Call: '.$event['endpoint'], array(
				    'method' => $event['method'],
				    'params' => $event['params'],
				    'request' => $event['request'],
			    ));
			});

			self::$api->onError(function($error, $event){
			    SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_ERROR, 'ucm', 'API Error: '.$event['endpoint']. ' ('.$error.')', $event);
			});


			//$wpClient->setCredentials($this->get( 'ucm_wordpress_xmlrpc' ), 'username', 'password');

		}
		return self::$api;
	}
	public function get_api_user_to_id($wp_user_id){
		if((int)$wp_user_id > 0) {
		    $wordpress_user = $this->get_api_user($wp_user_id);
		    /* Array ( [user_id] => 1442 [username] => palumboe1 [registered] => stdClass Object ( [scalar] => 20150303T19:24:05 [xmlrpc_type] => datetime [timestamp] => 1425410645 ) [email] => palumboe1@gmail.com [nicename] => palumboe1 [display_name] => palumboe1 [support_hub] => done ) */
		    if($wordpress_user && !empty($wordpress_user['user_id']) && $wordpress_user['user_id'] == $wp_user_id){
			    $comment_user = new SupportHubUser_ucm();
			    $res = false;
			    $wordpress_user['email'] = trim(strtolower($wordpress_user['email']));
			    if(!empty($wordpress_user['email'])){
				    $res = $comment_user->load_by( 'user_email', $wordpress_user['email']);
			    }
			    if(!$res) {
				    $comment_user->create_new();
				    $comment_user->update( 'user_email', $wordpress_user['email'] );
				    if ( ! $comment_user->get( 'user_username' ) ) {
					    $comment_user->update( 'user_username', $wordpress_user['username'] );
				    }
			    }
			    $user_data = $comment_user->get('user_data');
				if(!is_array($user_data))$user_data=array();
			    /*$user_data['source'] = array_merge(isset($user_data['source']) ? $user_data['source'] : array(), array(
				    'ucm'
			    ));*/
			    if(!empty($wordpress_user['envato_codes'])){
				    if(!isset($user_data['envato_codes']))$user_data['envato_codes']=array();
				    $user_data['envato_codes'] = array_merge($user_data['envato_codes'], $wordpress_user['envato_codes']);
			    }
			    $comment_user->update_user_data($user_data);
			    return $comment_user->get('shub_ucm_user_id');
		    }
	    }
		return false;
	}
	public function get_api_user($wp_user_id){
		if(!(int)$wp_user_id)return false;
		// seed the cache with the latest existing user details.
		// generally the posts will come from recent users so it's quicker to get this bulk list of recent users and loop through that, compared to hitting the API for each user details on each product post.
		$filter_user = array(
			'number' => 100,
		);
		$api = $this->get_api();
		//$api_user = $api->getUser($wp_user_id,array('username','basic','envato_codes')); print_r($api_user); exit;
		$api_result_latest_users = $this->get_api_cache($filter_user);
		if(!$api_result_latest_users){
			$api_users = $api->getUsers($filter_user,array('basic','envato_codes'));
			$api_result_latest_users = array();
			foreach($api_users as $api_user){
				$api_result_latest_users[$api_user['user_id']] = $api_user;
			}
			unset($api_users);
		}
		// if this user doesn't exist in the latest listing we grab it
		if(!isset($api_result_latest_users[$wp_user_id])){
			$api_user = $api->getUser($wp_user_id,array('basic','envato_codes'));
			if($api_user && $api_user['user_id'] == $wp_user_id){
				$api_result_latest_users[$api_user['user_id']] = $api_user;
			}
		}
		$this->set_api_cache($filter_user, $api_result_latest_users);
		return isset($api_result_latest_users[$wp_user_id]) ? $api_result_latest_users[$wp_user_id] : false;

	}
	private static $api_cache = array();
	public function get_api_cache($filter){
		$key = md5(serialize($filter));
		if(isset(self::$api_cache[$key]))return self::$api_cache[$key];
		return false;
	}
	public function set_api_cache($filter,$data){
		$key = md5(serialize($filter));
		self::$api_cache[$key] = $data;
	}

	public function get_picture(){
		$data = $this->get('ucm_data');
		return $data && isset($data['pictureUrl']) && !empty($data['pictureUrl']) ? $data['pictureUrl'] : false;
	}
	

	/**
	 * Links for wordpress
	 */
	public function link_connect(){
		return 'admin.php?page=support_hub_settings&tab=ucm&ucm_do_oauth_connect&shub_ucm_id='.$this->get('shub_ucm_id');
	}
	public function link_edit(){
		return 'admin.php?page=support_hub_settings&tab=ucm&shub_ucm_id='.$this->get('shub_ucm_id');
	}
	public function link_new_message(){
		return 'admin.php?page=support_hub_main&shub_ucm_id='.$this->get('shub_ucm_id').'&shub_ucm_message_id=new';
	}


	public function link_refresh(){
		return 'admin.php?page=support_hub_settings&tab=ucm&manualrefresh&shub_ucm_id='.$this->get('shub_ucm_id').'&ucm_stream=true';
	}

}
