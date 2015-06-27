<?php

class SupportHubUser{

	public function __construct($shub_user_id = false){
		if($shub_user_id){
			$this->load($shub_user_id);
		}
	}

	private $shub_user_id = false; // the current user id in our system.
    public $details = array();
	private $json_fields = array('user_data');

	public $db_table = 'shub_user'; // overwritten by individual network user classes
	public $db_primary_key = 'shub_user_id'; // overwritten by individual network user classes

	public function reset(){
		$this->{$this->db_primary_key} = false;
		$this->details = array(
			'shub_user_id' => '',
			'user_fname' => '',
			'user_lname' => '',
			'user_username' => '',
			'user_email' => '',
			'shub_linked_user_id' => 0,
			'user_data' => array(),
		);
		foreach($this->details as $field_id => $field_data){
			$this->{$field_id} = $field_data;
		}
	}

	public function create_new(){
		$this->reset();
		$this->{$this->db_primary_key} = shub_update_insert($this->db_primary_key,false,$this->db_table,array());
		$this->load($this->{$this->db_primary_key});
	}

	private static $_latest_load_create = array();
	public function load_by($field, $value){
		$this->reset();
		if(!empty($field) && !empty($value) && isset($this->details[$field])){
			if(isset(self::$_latest_load_create[$field][$value]) && self::$_latest_load_create[$field][$value] > 0){
				$this->load(self::$_latest_load_create[$field][$value]);
				return true;
			}
			$data = shub_get_single($this->db_table,$field,$value);
			if(!$data){
				// check if it was recently created? gets around weird WP caching issue, resuling in mass duplicate of user details on bulk import
				if(!isset(self::$_latest_load_create[$field]))self::$_latest_load_create[$field]=array();
				self::$_latest_load_create[$field][$value] = false; // pending creating maybe?
			}else if($data && isset($data[$field]) && $data[$field] == $value && $data[$this->db_primary_key]){
				$this->load($data[$this->db_primary_key]);
				return true;
			}
		}
		return false;
	}

    public function load($shub_user_id = false){
	    if(!$shub_user_id)$shub_user_id = $this->{$this->db_primary_key};
	    $this->reset();
	    $this->{$this->db_primary_key} = $shub_user_id;
        if($this->{$this->db_primary_key}){
	        $data = shub_get_single($this->db_table,$this->db_primary_key,$this->{$this->db_primary_key});
	        foreach($this->details as $key=>$val){
		        $this->details[$key] = $data && isset($data[$key]) ? $data[$key] : $val;
		        if(in_array($key,$this->json_fields)){
			        $this->details[$key] = @json_decode($this->details[$key],true);
			        if(!is_array($this->details[$key]))$this->details[$key] = array();
		        }
	        }
	        if(!is_array($this->details) || $this->details[$this->db_primary_key] != $this->{$this->db_primary_key}){
		        $this->reset();
		        return false;
	        }
        }
        foreach($this->details as $key=>$val){
            $this->{$key} = $val;
        }
        return $this->{$this->db_primary_key};
    }

	public function get($field){
		return isset($this->{$field}) ? $this->{$field} : false;
	}

    public function update($field,$value){
	    // what fields to we allow? or not allow?
	    if(in_array($field,array($this->db_primary_key)))return;
        if($this->{$this->db_primary_key}){
            $this->{$field} = $value;
	        if(isset(self::$_latest_load_create[$field][$value])){
		        self::$_latest_load_create[$field][$value] = $this->{$this->db_primary_key};
	        }
	        if(in_array($field,$this->json_fields)){
		        $value = json_encode($value);
	        }
            shub_update_insert($this->db_primary_key,$this->{$this->db_primary_key},$this->db_table,array(
	            $field => $value,
            ));
        }
    }

	public function update_user_data($user_data){
		if(is_array($user_data)){
			// yes, this member has some items, save these items to the account ready for selection in the settings area.
			$save_data = $this->get('user_data');
			if(!is_array($save_data))$save_data=array();
			$save_data = array_merge($save_data,$user_data);
			$this->update('user_data',$save_data);
		}
	}
	public function delete(){
		if($this->{$this->db_primary_key}) {
			shub_delete_from_db( $this->db_table, $this->db_primary_key, $this->{$this->db_primary_key} );
		}
	}

	public function get_link(){
		return '#';
	}
	public function get_image(){
		if($this->get('user_email')){
			$hash = md5(trim($this->get('user_email')));
			return '//www.gravatar.com/avatar/'.$hash.'?d=wavatar';
		}
		return '';
	}
	public function get_name(){
		return $this->get('user_username');
	}

}
