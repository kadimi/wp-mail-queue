<?php

namespace Kadimi;

use PHPMailer;
use Kadimi\WPMail;

/**
* WPMailQueue class.
*/
class WPMailQueue {

	/**
	 * This package name
	 * @var String
	 */
	private $package = __CLASS__;

	/**
	 * The only instance that will ever exist.
	 * @var WPMailQueue
	 */
	protected static $instance;

	/**
	 * Should emails be caught.
	 * @var Boolean
	 */
	private $catch = true;

	/**
	 * Should emails be processed.
	 * @var Boolean
	 */
	private $process = true;

	/**
	 * Number of messages to send per processing operation.
	 * @var Integer
	 */
	private $limit = 2;

	/**
	 * Contains emails caught.
	 * @var Array
	 */
	private $queue;

	/**
	 * Some statistics.1
	 * @var Array
	 */
	private $stats = [ 'sent' => 0, 'caught' => 0, ];

	/**
	 * Main WPMailQueue Instance.
	 *
	 * Ensures only one instance of WooCommerce is loaded or can be loaded.
	 *
	 * @return WPMailQueue
	 */
	public static function instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Filters some properties and enqueues processing at the end of the request.
	 */
	public function __construct() {
		$this->catch = apply_filters( $this->optionName( '::' . __FUNCTION__ . '\$catch' ), $this->catch );
		$this->limit = apply_filters( $this->optionName( '::' . __FUNCTION__ . '\$limit' ), $this->limit );
		$this->process = apply_filters( $this->optionName( '::' . __FUNCTION__ . '\$process' ), $this->process );
		$this->loadQueue();
		if ( $this->process ) {
			add_action( 'shutdown', [ $this, 'process' ] );
		}
	}

	private function getOption( $option, $default = null, $uses_json = false ) {
		if ( is_null( $default ) && $uses_json ) {
			$default = '[]';
		}
		$value = get_option( $this->optionName( $option ), $default );
		$value = $uses_json ? json_decode( $value, true ) : $value;
		return $value;
	}

	private function updateOption( $option, $value, $uses_json = false ) {
		$value = $uses_json ? json_encode( $value, JSON_PRETTY_PRINT ) : $value;
		return update_option( $this->optionName( $option ), $value );
	}

	private function optionName( $option ) {
		return $this->fullStandardName( $option );
	}

	private function fullStandardName( $option ) {
		return sprintf( '%s\%s', $this->package, $option );
	}

	private function loadQueue() {
		$queue = $this->getOption( 'queue', null, true );
		$this->queue = is_array( $queue ) ? $queue : [];
	}

	private function updateQueue() {
		$this->updateOption( 'queue', $this->queue, true );
	}

	private function appendToQueue( $atts ) {

		$atts['meta'] = [
			'unique_id' =>  uniqid( $this->package . '-', true ),
			'time' => time(),
		];

		$this->queue[] = $atts;
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
		$atts = compact( 'to', 'subject', 'message', 'headers', 'attachments' );
		if ( $this->catch ) {
			$this->catch( $atts );
			return true;
		} else {
			return $this->send( $atts );
		}
	}

	private function catch( $atts ) {
		$this->appendToQueue( $atts );
		$this->stats['caught']++;
	}

	private function send( $atts ) {
		extract( $atts );
		WPMail::send( $to, $subject, $message, $headers, $attachments );
		$this->stats['sent']++;
		return true;
	}

	public function process() {
		while ( $this->stats['sent'] < $this->limit && $atts = $this->getFirstInQueue() ) {
			$this->send( $atts );
		}
	}
}
