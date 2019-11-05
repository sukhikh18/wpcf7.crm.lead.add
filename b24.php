<?php
/**
 * Plugin Name: Contact form to Bitrix24 lead
 * Plugin URI: https://github.com/nikolays93
 * Description: Insert lead entity into bitrix on submit (sent) form message.
 * Version: 0.1.1
 * Author: NikolayS93
 * Author URI: https://vk.com/nikolays_93
 * Author EMAIL: NikolayS93@ya.ru
 * License: GNU General Public License v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cfb24lead
 * Domain Path: /languages/
 *
 * @package wpcf7.crm.lead.add
 */

if ( ! defined( 'BITRIX24_SUBDOMAIN' ) ) {
	define( 'BITRIX24_SUBDOMAIN', 'seo18ru' );
}

if ( ! defined( 'BITRIX24_USER_ID' ) ) {
	define( 'BITRIX24_USER_ID', 1 );
}

if ( ! defined( 'BITRIX24_TOKEN' ) ) {
	define( 'BITRIX24_TOKEN', '1234l1pofb8huv' );
}

if ( ! function_exists( 'get_b24_api_url' ) ) {
	function get_b24_api_url( $method, $answer_type = 'json' ) {
		$sub     = BITRIX24_SUBDOMAIN;
		$token   = BITRIX24_TOKEN;
		$user_id = BITRIX24_USER_ID;

		return "https://{$sub}.bitrix24.ru/rest/{$user_id}/{$token}/{$method}.{$answer_type}";
	}
}

if ( ! function_exists( 'wpcf7_b24_send_lead' ) ) {
	/**
	 * Выполняется после отправки сообщения WPCF7
	 *
	 * @param WPCF7_ContactForm $WPCF7_ContactForm [description]
	 * @param  [type] $abort             [description]
	 * @param WPCF7_Submission $WPCF7_Submission [description]
	 *
	 * @return void
	 */
	function wpcf7_b24_send_lead( $WPCF7_ContactForm, $abort = null, $WPCF7_Submission = null ) {
		if ( ! $WPCF7_Submission ) {
			$WPCF7_Submission = WPCF7_Submission::get_instance();
		}

		/** @var array $posted_data Значения переданные формой WPCF7 */
		$posted_data = $WPCF7_Submission->get_posted_data();

		/** @var array $posted_fields поля которые нужно приянть из $posted_data */
		$posted_fields = array(
			'NAME'     => 'your-name',
			'PHONE'    => 'your-phone',
			'EMAIL'    => 'your-email',
			'COMMENTS' => 'your-message',

			'UTM_SOURCE'   => 'utm_source',
			'UTM_MEDIUM'   => 'utm_medium',
			'UTM_CAMPAIGN' => 'utm_campaign',
			'UTM_TERM'     => 'utm_term',
			/**
			 * ID кастомного поля в B24
			 *
			 * @url <...>.bitrix24.ru/crm/configs/fields/CRM_LEAD/
			 */
			// 'UF_CRM_12345' => 'cid',
		);

		// if ( 5 == $WPCF7_ContactForm->id ) {
			send_b24_lead( $posted_data, $posted_fields, array(
				'TITLE' => 'Веб форма: ' . $WPCF7_ContactForm->title,
			) );
		// }
	}
}

add_action( 'wpcf7_mail_sent', 'wpcf7_b24_send_lead', 10, 3 );

if ( ! function_exists( 'send_b24_lead' ) ) {
	function send_b24_lead( $posted_data, $_fields, $additionals ) {
		// Принимаем значения из $posted_data (обработанный $_POST массив)
		$fields = array_map( function ( $value ) use ( $posted_data ) {
			return isset( $posted_data[ $value ] ) ? $posted_data[ $value ] : '';
		}, $_fields );

		// Добавляем все оставшиеся поля в комментарии
		if ( ! empty( $fields['COMMENTS'] ) ) {
			$fields['COMMENTS'] .= "\r\n____________________________________________";
			$fields['COMMENTS'] .= "\r\n";

			array_walk( $posted_data, function ( $val, $key ) use ( &$fields, $_fields ) {
				if ( ! in_array( $key, $_fields ) && $val ) {
					$fields['COMMENTS'] .= "$key: $val.\r\n";
				}
			} );
		}

		// Переводим номер телефона в многомерный массив
		if ( ! empty( $fields['PHONE'] ) ) {
			$phone = array(
				'VALUE'      => $fields['PHONE'],
				'VALUE_TYPE' => 'WORK',
			);

			$fields['PHONE'] = array( $phone );
		}

		// Переводим email в многомерный массив
		if ( ! empty( $fields['EMAIL'] ) ) {
			$email = array(
				'VALUE'      => $fields['EMAIL'],
				'VALUE_TYPE' => 'WORK',
			);

			$fields['EMAIL'] = array( $email );
		}

		// Если хотим убрать пустые значения
		// $fields = array_filter( $fields );

		$fields = wp_parse_args( array_merge( $fields, $additionals ), array(
			'TITLE'          => 'Лид с веб формы сайта',
			'STATUS_ID'      => 'NEW',
			'ASSIGNED_BY_ID' => BITRIX24_USER_ID,
			'SOURCE_ID'      => 'WEB'
		) );

		$params = array( "REGISTER_SONET_EVENT" => "Y" );

		$api_query_postfields = http_build_query( compact( 'fields', 'params' ) );

		// запрос к B24 при помощи функции curl_exec
		$curl = curl_init();
		curl_setopt_array( $curl, array(
			CURLOPT_SSL_VERIFYPEER => 0,
			CURLOPT_POST           => 1,
			CURLOPT_HEADER         => 0,
			CURLOPT_RETURNTRANSFER => 1, // Вернуть ответ
			CURLOPT_URL            => get_b24_api_url( 'crm.lead.add' ),
			CURLOPT_POSTFIELDS     => $api_query_postfields,
		) );

		$result = curl_exec( $curl );
		curl_close( $curl );

		// Do u need answer?
		// $answer = json_decode( $result, $in_array = true );
	}
}
