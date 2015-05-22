<?php

class SupportHub_network{


	public $id;
	public $friendly_name;
	public $desc;

	public function __construct( ) {
		$this->reset();
	}
	private function reset(){}


	public function init(){}
	public function get_unread_count(){}
	public function init_menu(){}
	public function page_assets(){}
	public function settings_page(){}
	public function is_enabled(){
		return get_option('shub_manager_enabled_'.$this->id,0);
	}
	public function get_message_details($message_id){return array();}
	public function get_install_sql(){return '';}


	public $all_messages = false;
	public $limit_start = 0;
	public $search_params = array();
	public $search_order = array();
	public $search_limit = 0;
	public function load_all_messages($search=array(),$order=array(),$limit_batch=0){}

	public $get_next_message_failed = false;
	public function get_next_message(){
		if(empty($this->all_messages) && !$this->get_next_message_failed){
			// seed the next batch of messages.
			$this->load_all_messages($this->search_params, $this->search_order, $this->search_limit);
			if(empty($this->all_messages)){
				// seed failed, we're completely out of messages from this one.
				// mark is as failed so we don't keep hitting sql
				$this->get_next_message_failed = true;
			}
		}
		return !empty($this->all_messages) ? array_shift($this->all_messages) : false;
		/*if(mysql_num_rows($this->all_messages)){
			return mysql_fetch_assoc($this->all_messages);
		}
		return false;*/
	}


}