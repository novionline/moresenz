<?php

namespace Smush\Core\Media_Library;

use Smush\Core\Bulk\Background_Bulk_Smush_Controller;

class Media_Library_Last_Process_Pro extends Media_Library_Last_Process {
	public function __construct() {
		parent::__construct();

		if ( ! $this->should_track() ) {
			return;
		}
		// Background Bulk Smush.
		$this->register_action( 'wp_smush_bulk_smush_dead', array( $this, 'record_process_end_time' ), 5 );

		$bulk_smush_background_process = Background_Bulk_Smush_Controller::get_instance()->get_background_process();
		$this->register_action( $bulk_smush_background_process->action_name( 'cron' ), array( $this, 'check_bulk_smush_process' ), 5 );
	}

	public function should_run() {
		return Background_Bulk_Smush_Controller::get_instance()->should_use_background();
	}
}