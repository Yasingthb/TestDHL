<?php

use SkyVerge\WooCommerce\Facebook\Events\Event;

/**
 * 1- DHL Statusleri Bul ('delivered','','','') _/
 * 2- WooCommerce Status Bul ('wc-completed','','') _/
 * 3- DHL - WC Status eşleştirilcek. _/
 * 4- Eşleştirilen statuse istinaden wooKargo ayarlarından sms şablonu getirilecek.
 * 5- Getirilen Şablon Twillio sms ile gönderilecek.
 */

/*
 *sipariş alındı -> karşılığı yok sanırım ödeme gerçekleşince yapacagız.
 *Shipment information received->Kargo hazırlanıyor
 * sipariş kargolandı -> transit;
 *  Kurye dağıtımına cıktı->order is out for courier delivery
 */

class DHL_Tracking
{

	public function __construct()
	{
		add_action('woocommerce_order_status_completed', array($this, 'order_status_completed'));
		add_action('init', array($this, 'order_status_completed'));
	}



	public function order_status_completed($order_id)
	{
		$order_id='22';
		//mysite_woocommerce_order_status_completed
		$order		   = wc_get_order($order_id);

		$tracking_id   = $this->get_tracking_id($order_id);

		$order_status  = $order->get_status();
		//processing -- pre-cargo -- send-cargo

		$dhl_send_sms = new WK_SMSGonder();
		$sablonlar = get_option('wkSmsSablonlari');


		if ($tracking_id) {


			$status = $this->get_status_from_dhl($tracking_id);


			if ($status->events[0]->description) {
				$get_order_phone_number = get_post_meta($order_id, '_billing_phone', true);

				$old_description = get_post_meta($order_id, 'dhl_description', true);

				$cargo_stats = array('kargo_firma' => 'DHL ' . ($status->service), 'kargo_takip_no' => "{$tracking_id}", 'kargo_takip_link' => "{$this->get_cargo_urli($order_id)}", 'kargo_tarih' => "{$status->status->timestamp}");
				var_dump($cargo_stats);
				die;
				update_post_meta($order_id, 'wookargo_kargo_bilgileri', $cargo_stats, true);

				if ($status->events[0]->description !== $old_description) {

					$order->add_order_note("DHL Message: {$status->events[0]->description}");

					update_post_meta($order_id, 'dhl_status', $status->events[0]->status);
					update_post_meta($order_id, 'dhl_status_code', $status->events[0]->statusCode);
					update_post_meta($order_id, 'dhl_description', $status->events[0]->description);
				
				
					if ($status->events[0]->description == 'Shipment picked up') {

						$dhl_send_sms->smsGonder($sablonlar['processing'], $get_order_phone_number, $order_id);
					} else if ($status->events[0]->description == 'Shipment information received') {


						$dhl_send_sms->smsGonder($sablonlar['pre-cargo'], $get_order_phone_number, $order_id);
					} else if (strstr($status->events[0]->description, 'Shipment has departed from a DHL facility')) {

						$dhl_send_sms->smsGonder($sablonlar['send-cargo'], $get_order_phone_number, $order_id);
					} else if ($status->events[0]->description == 'Shipment is out with courier for delivery') {

						$dhl_send_sms->smsGonder($sablonlar['completed'], $get_order_phone_number, $order_id);
					}
				}

				if ($status->events[0]->status !== 'delivered') {
					wp_schedule_single_event(time() + 60, 'dhl_status_control', array($order_id));
				}
			}
		}
	}

	function get_tracking_id($order_id)
	{

		global $wpdb;
		$tracking_id = false;
		$trackling   = $wpdb->get_var("SELECT comment_content FROM {$wpdb->comments} WHERE comment_post_ID = {$order_id} AND comment_content LIKE '%tracking%'");
		if ($trackling) {
			$trackling = explode('id=', $trackling);
			if (!empty($trackling)) {
				$tracking_id = $trackling[1];
			}
		}

		return $tracking_id;
	}
	function get_cargo_urli($order_id)
	{
		global $wpdb;
		$get_url = false;
		$urling   = $wpdb->get_var("SELECT comment_content FROM {$wpdb->comments} WHERE `comment_post_ID` = {$order_id} AND comment_content LIKE '%is: %'");
		if ($urling) {
			$urling = explode('is: ', $urling);
			if (!empty($urling)) {
				$get_url = $urling[2];
			}
		}

		return $get_url;
	}

	function get_status_from_dhl($tracking_id)
	{
		$args     = array(
			'headers' => array(
				'DHL-API-Key' => 'p4MCNbE3GtcGiXgoDNhl4mQi8tFAxn5n',
			),
		);
		$response = wp_remote_get("https://api-eu.dhl.com/track/shipments?trackingNumber={$tracking_id}", $args);
		$response = json_decode($response['body']);
		
		return $response->shipments[0];
	}
}
