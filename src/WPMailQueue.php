<?php

namespace Kadimi;

use PHPMailer;
use Kadimi\WPMail;

/**
* WPMailQueue class.
*/
class WPMailQueue {

	protected static $instance;
	protected $package;
	protected $queue;
	protected $stats;

	public static function instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		$this->package = __CLASS__;
		$this->loadQueue();
		$this->stats = [
			'sent' => 0,
		];
		add_action( 'init', [ $this, 'process' ] );
	}

	private function getOption( $option, $default = null ) {
		return get_option( $this->optionName( $option ), $default );
	}

	private function updateOption( $option, $value ) {
		return update_option( $this->optionName( $option ), $value );
	}

	private function optionName( $option ) {
		return $this->fullStandardName( $option );
	}

	private function fullStandardName( $option ) {
		return sprintf( '%s\%s', $this->package, $option );
	}

	private function loadQueue() {
		$queue = $this->getOption( 'queue' );
		$this->queue = is_array( $queue ) ? $queue : [];
	}

	private function updateQueue() {
		$this->updateOption( 'queue', $this->queue );
	}

	private function appendToQueue( $to, $subject, $message, $headers, $attachments ) {
		$this->queue[] = [ $to, $subject, $message, $headers, $attachments ];
		$this->updateQueue();
	}

	private function getFirstInQueue() {
		$atts = array_shift( $this->queue );
		if ( $atts ) {
			$this->updateQueue();
		}
		return $atts ?? null;
	}

	public function WPMail( $to, $subject, $message, $headers, $attachments ) {

		$catch = apply_filters( $this->optionName( '::WPMail\$catch' ), true );
		if ( $catch ) {
			$this->catch( $to, $subject, $message, $headers, $attachments );
		} else {
			$this->send( $to, $subject, $message, $headers, $attachments );			
		}

		return [];
	}

	private function catch( $to, $subject, $message, $headers, $attachments ) {

		$catch = apply_filters( $this->optionName( '::catch\$catch' ), true );
		if ( ! $catch ) {
			return;
		}

		$this->appendToQueue( $to, $subject, $message, $headers, $attachments );
	}

	private function send( $to, $subject, $message, $headers = '', $attachments = array() ) {
		$this->stats['sent']++;
		return WPMail::send( $to, $subject, $message, $headers, $attachments );
	}

	public function process() {

		$process = apply_filters( $this->optionName( '::process\$process' ), true );
		if ( ! $process ) {
			return;
		}

		while ( $atts = $this->getFirstInQueue() ) {
			list( $to, $subject, $message, $headers, $attachments ) = $atts;
			$this->send( $to, $subject, $message, $headers, $attachments );
		}
	}
}
