<?php
/**
 * Plugin Name: Bluepay WooCommerce Addon
 * Plugin URI: 
 * Description: This plugin adds a payment option in WooCommerce for customers to pay with their Credit Cards Via Bluepay.
 * Version: 1.0.0
 * Author: Syed Nazrul Hassan
 * Author URI: https://nazrulhassan.wordpress.com/
 * License: GPLv2
 */
 
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
function bluepay_init()
{

include(plugin_dir_path( __FILE__ )."class/BluePay.php");

function add_bluepay_gateway_class( $methods ) 
{
	$methods[] = 'WC_bluepay_Gateway'; 
	return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'add_bluepay_gateway_class' );

if(class_exists('WC_Payment_Gateway'))
{
	class WC_bluepay_Gateway extends WC_Payment_Gateway 
	{
		
		public function __construct()
		{

		$this->id               = 'bluepay';
		$this->icon             = plugins_url( 'images/bluepay.png' , __FILE__ ) ;
		$this->has_fields       = true;
		$this->method_title     = 'Bluepay Cards Settings';		
		$this->init_form_fields();
		$this->init_settings();
		$this->supports                     = array(  'products',  'refunds');
		$this->title			           = $this->get_option( 'bluepay_title' );
		$this->bluepay_apilogin        = $this->get_option( 'bluepay_apilogin' );
		$this->bluepay_transactionkey  = $this->get_option( 'bluepay_transactionkey' );
		$this->bluepay_sandbox         = $this->get_option( 'bluepay_sandbox' ); 
		$this->bluepay_authorize_only  = $this->get_option( 'bluepay_authorize_only' ); 
		$this->bluepay_cardtypes       = $this->get_option( 'bluepay_cardtypes'); 
	

		if(!defined("BLUEPAY_MODE"))
		{ define("BLUEPAY_MODE", ($this->bluepay_sandbox =='yes'? 'TEST':'LIVE' )); }
		if(!defined("BLUEPAY_TRANSACTION_MODE"))
		{ define("BLUEPAY_TRANSACTION_MODE", ($this->bluepay_authorize_only =='yes'? true : false));}
		
		
	
		if(!defined("BLUEPAY_ACCOUNT_ID"))
		{define("BLUEPAY_ACCOUNT_ID",    $this->bluepay_apilogin );       }
		if(!defined("BLUEPAY_TRANSACTION_KEY"))
		{define("BLUEPAY_TRANSACTION_KEY", $this->bluepay_transactionkey ); }
			
		
		if (is_admin()) 
		{
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}
		}

		public function admin_options()
		{
		?>
		<h3><?php _e( 'Bluepay addon for WooCommerce', 'woocommerce' ); ?></h3>
		<p><?php  _e( 'Bluepay is a payment gateway service provider allowing merchants to accept credit card.', 'woocommerce' ); ?></p>
		<table class="form-table">
		  <?php $this->generate_settings_html(); ?>
		</table>
		<?php
		}


		public function init_form_fields()
		{

		$this->form_fields = array(
		'enabled' => array(
		  'title' => __( 'Enable/Disable', 'woocommerce' ),
		  'type' => 'checkbox',
		  'label' => __( 'Enable Bluepay', 'woocommerce' ),
		  'default' => 'yes'
		  ),
		'bluepay_title' => array(
		  'title' => __( 'Title', 'woocommerce' ),
		  'type' => 'text',
		  'description' => __( 'This controls the title which the buyer sees during checkout.', 'woocommerce' ),
		  'default' => __( 'Bluepay', 'woocommerce' ),
		  'desc_tip'      => true,
		  ),
		'bluepay_apilogin' => array(
		  'title' => __( 'Account ID', 'woocommerce' ),
		  'type' => 'text',
		  'description' => __( 'This is the Account ID of Bluepay.', 'woocommerce' ),
		  'default' => '',
		  'desc_tip'      => true,
		  'placeholder' => 'Bluepay Account ID'
		  ),
		
		'bluepay_transactionkey' => array(
		  'title' => __( 'Transaction Key', 'woocommerce' ),
		  'type' => 'text',
		  'description' => __( 'This is the Transaction Key of Bluepay', 'woocommerce' ),
		  'default' => '',
		  'desc_tip'      => true,
		  'placeholder' => 'Bluepay Transaction Key'
		  ),
		
		'bluepay_sandbox' => array(
		  'title'       => __( 'Bluepay sandbox', 'woocommerce' ),
		  'type'        => 'checkbox',
		  'label'       => __( 'Enable Bluepay sandbox (Live Mode if Unchecked)', 'woocommerce' ),
		  'description' => __( 'If checked its in sanbox mode and if unchecked its in live mode', 'woocommerce' ),
		  'desc_tip'      => true,
		  'default'     => 'no'
		),
		'bluepay_authorize_only' => array(
				'title'       => __( 'Authorize Only', 'woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Authorize Only Mode (Authorize & Capture If Unchecked)', 'woocommerce' ),
				'description' => __( 'If checked will only authorize the credit card only upon checkout.', 'woocommerce' ),
				'desc_tip'      => true,
				'default'     => 'no',
				),
		'bluepay_cardtypes' => array(
			 'title'    => __( 'Accepted Cards', 'woocommerce' ),
			 'type'     => 'multiselect',
			 'class'    => 'chosen_select',
			 'css'      => 'width: 350px;',
			 'desc_tip' => __( 'Select the card types to accept.', 'woocommerce' ),
			 'options'  => array(
				'mastercard'       => 'MasterCard',
				'visa'             => 'Visa',
				'discover'         => 'Discover',
				'amex' 		    => 'American Express',
				'jcb'		    => 'JCB',
				'dinersclub'       => 'Dinners Club',
			 ),
			 'default' => array( 'mastercard', 'visa', 'discover', 'amex' ),
		),

		
		
	  );
  		}


  		/*Is Avalaible*/
  		public function is_available() {
		$order = null;


		 if(empty($this->bluepay_apilogin) || empty($this->bluepay_transactionkey)) {
			 		return false;
			 }


  		if ( ! empty( $this->bluepay_enable_for_methods ) ) {

			// Only apply if all packages are being shipped via local pickup
			$chosen_shipping_methods_session = WC()->session->get( 'chosen_shipping_methods' );

			if ( isset( $chosen_shipping_methods_session ) ) {
				$chosen_shipping_methods = array_unique( $chosen_shipping_methods_session );
			} else {
				$chosen_shipping_methods = array();
			}

			$check_method = false;

			if ( is_object( $order ) ) {
				if ( $order->shipping_method ) {
					$check_method = $order->shipping_method;
				}

			} elseif ( empty( $chosen_shipping_methods ) || sizeof( $chosen_shipping_methods ) > 1 ) {
				$check_method = false;
			} elseif ( sizeof( $chosen_shipping_methods ) == 1 ) {
				$check_method = $chosen_shipping_methods[0];
			}

			if ( ! $check_method ) {
				return false;
			}

			$found = false;

			foreach ( $this->bluepay_enable_for_methods as $method_id ) {
				if ( strpos( $check_method, $method_id ) === 0 ) {
					$found = true;
					break;
				}
			}

			if ( ! $found ) {
				return false;
			}	

		}

			return parent::is_available();
		}
  		/*end is availaible*/


  		/*Get Icon*/
		public function get_icon() {
		$icon = '';
		if(is_array($this->bluepay_cardtypes ))
		{
        foreach ( $this->bluepay_cardtypes  as $card_type ) {

				if ( $url = $this->get_payment_method_image_url( $card_type ) ) {
					
					$icon .= '<img src="'.esc_url( $url ).'" alt="'.esc_attr( strtolower( $card_type ) ).'" />';
				}
			}
		}
		else
		{
			$icon .= '<img src="'.esc_url( plugins_url( 'images/bluepay.png' , __FILE__ ) ).'" alt="Bluepay Payment Gateway" />';	  
		}

         return apply_filters( 'woocommerce_bluepay_icon', $icon, $this->id );
		}
 
		public function get_payment_method_image_url( $type ) {

		$image_type = strtolower( $type );
				return  WC_HTTPS::force_https_url( plugins_url( 'images/' . $image_type . '.png' , __FILE__ ) ); 
		}
		/*Get Icon*/


	

		/*Get Card Types*/
		function get_card_type($number)
		{
		
		    $number=preg_replace('/[^\d]/','',$number);
		    if (preg_match('/^3[47][0-9]{13}$/',$number))
		    {
		        return 'amex';
		    }
		    elseif (preg_match('/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/',$number))
		    {
		        return 'dinersclub';
		    }
		    elseif (preg_match('/^6(?:011|5[0-9][0-9])[0-9]{12}$/',$number))
		    {
		        return 'discover';
		    }
		    elseif (preg_match('/^(?:2131|1800|35\d{3})\d{11}$/',$number))
		    {
		        return 'jcb';
		    }
		    elseif (preg_match('/^5[1-5][0-9]{14}$/',$number))
		    {
		        return 'mastercard';
		    }
		    elseif (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/',$number))
		    {
		        return 'visa';
		    }
		    else
		    {
		        return 'unknown card';
		    }
		}// End of getcard type function



		     /*Start of credit card form */
  		public function payment_fields() {
			$this->form();
		}

  		public function field_name( $name ) {
		return $this->supports( 'tokenization' ) ? '' : ' name="' . esc_attr( $this->id . '-' . $name ) . '" ';
	}

  		public function form() {
		wp_enqueue_script( 'wc-credit-card-form' );
		$fields = array();
		$cvc_field = '<p class="form-row form-row-last">
			<label for="' . esc_attr( $this->id ) . '-card-cvc">' . __( 'Card Code', 'woocommerce' ) . ' <span class="required">*</span></label>
			<input id="' . esc_attr( $this->id ) . '-card-cvc" class="input-text wc-credit-card-form-card-cvc" type="text" autocomplete="off" placeholder="' . esc_attr__( 'CVC', 'woocommerce' ) . '" ' . $this->field_name( 'card-cvc' ) . '/>
		</p>';
		$default_fields = array(
			'card-number-field' => '<p class="form-row form-row-wide">
				<label for="' . esc_attr( $this->id ) . '-card-number">' . __( 'Card Number', 'woocommerce' ) . ' <span class="required">*</span></label>
				<input id="' . esc_attr( $this->id ) . '-card-number" class="input-text wc-credit-card-form-card-number" type="text" maxlength="20" autocomplete="off" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" ' . $this->field_name( 'card-number' ) . ' />
			</p>',
			'card-expiry-field' => '<p class="form-row form-row-first">
				<label for="' . esc_attr( $this->id ) . '-card-expiry">' . __( 'Expiry (MM/YY)', 'woocommerce' ) . ' <span class="required">*</span></label>
				<input id="' . esc_attr( $this->id ) . '-card-expiry" class="input-text wc-credit-card-form-card-expiry" type="text" autocomplete="off" placeholder="' . esc_attr__( 'MM / YY', 'woocommerce' ) . '" ' . $this->field_name( 'card-expiry' ) . ' />
			</p>',
			'card-cvc-field'  => $cvc_field
		);
		
		 $fields = wp_parse_args( $fields, apply_filters( 'woocommerce_credit_card_form_fields', $default_fields, $this->id ) );
		?>

		<fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-cc-form" class='wc-credit-card-form wc-payment-form'>
			<?php do_action( 'woocommerce_credit_card_form_start', $this->id ); ?>
			<?php
				foreach ( $fields as $field ) {
					echo $field;
				}
			?>
			<?php do_action( 'woocommerce_credit_card_form_end', $this->id ); ?>
			<div class="clear"></div>
		</fieldset>
		<?php
		
	}
  		/*End of credit card form*/


		
		public function process_payment( $order_id )
		{ 
		global $woocommerce;


		$wc_order 	= new WC_Order( $order_id );
		$amount     = $wc_order->order_total;
		$cardtype = $this->get_card_type(sanitize_text_field(str_replace(' ','',$_POST['bluepay-card-number'])));
			
         		if(!in_array($cardtype ,$this->bluepay_cardtypes ))
         		{
         			wc_add_notice('Merchant does not accept '.$cardtype.' card',  $notice_type = 'error' );
         			return false; die;
         		}
		
		
		$card_num         = sanitize_text_field(str_replace(' ','',$_POST['bluepay-card-number']));
		$exp_date         = explode( "/", sanitize_text_field($_POST['bluepay-card-expiry']));
		$exp_month        = str_replace( ' ', '', $exp_date[0]);
		$exp_year         = str_replace( ' ', '',$exp_date[1]);

		if (strlen($exp_year) == 4) {
            $exp_year =substr($exp_year, 2);
        }
		$cvc              = sanitize_text_field($_POST['bluepay-card-cvc']);
		
		$payment = new BluePay(BLUEPAY_ACCOUNT_ID,BLUEPAY_TRANSACTION_KEY,BLUEPAY_MODE);

		$payment->setCustomerInformation(
										array(
										'firstName' =>  $wc_order->billing_first_name,
										'lastName'  => $wc_order->billing_last_name, 
										'addr1' 	=> $wc_order->billing_address_1 ,    
										'addr2'		=> $wc_order->billing_address_2,
										'city' 		=> $wc_order->billing_city,
										'state' 	=> $wc_order->billing_state,  
										'zip' 		=> $wc_order->billing_postcode,    
										'country' 	=> $wc_order->billing_country,    
										'phone' 	=> $wc_order->billing_phone,
										'email' 	=> $wc_order->billing_email
										)
										);

		$payment->setCCInformation(
						array( 'cardNumber' =>  $card_num, 
							   'cardExpire' =>  $exp_month.$exp_year, 
							   'cvv2'       =>  $cvc 
							 )
						);

		// Passes value into INVOICE_ID field
    	$payment->setinvoiceID($wc_order->get_order_number()) ;
    	// Passes value into ORDER_ID field
    	$payment->setOrderID($wc_order->get_order_number()) ;

		
        if(yes == BLUEPAY_TRANSACTION_MODE)
        {
			$payment->auth($amount); 
		}
		else
		{
			$payment->sale($amount); 
		}
		$payment->process();

		if( $payment->isSuccessfulResponse()) 
		{
		
			if( "APPROVED" == $payment->getStatus() )
			{
			
			$wc_order->add_order_note( __( $payment->getMessage().' on '.date("d-m-Y h:i:s e"). ' with Transaction ID = '.$payment->getTransID().' AVS Response: '.$payment->getAVSResponse().' CVS Response: '.$payment->getCVV2Response().' Masked Account: '.$payment->getMaskedAccount().' Card Type: '.$payment->getCardType().' Authorization Code: '.$payment->getAuthCode()  , 'woocommerce' ) );
			$wc_order->payment_complete($payment->getTransID());
			WC()->cart->empty_cart();
			
			
			

				return array (
				  'result'   => 'success',
				  'redirect' => $this->get_return_url( $wc_order ),
				);
			}
			else 
			{
				
			$wc_order->add_order_note( __( $payment->getMessage()  , 'woocommerce' ) );	 
				wc_add_notice($payment->getMessage() , $notice_type = 'error' );
			}
		
		
		} 
		else 
		{
			$wc_order->add_order_note( __( $payment->getMessage(), 'woocommerce' ) );	 
			
			wc_add_notice($payment->getMessage(), $notice_type = 'error' );
		}
		
		} // end of function process_payment()
		

		

	}  // end of class WC_bluepay_Gateway

} // end of if class exist WC_Gateway

}

add_action( 'plugins_loaded', 'bluepay_init' );


function bluepay_woocommerce_addon_activate() {

	if(!function_exists('curl_exec'))
	{
		 wp_die( '<pre>This plugin requires PHP CURL library installled in order to be activated </pre>' );
	}
}
register_activation_hook( __FILE__, 'bluepay_woocommerce_addon_activate' );


/*Plugin Settings Link*/
function bluepay_woocommerce_settings_link( $links ) {
    $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=wc_bluepay_gateway">' . __( 'Settings' ) . '</a>';
    array_push( $links, $settings_link );
  	return $links;
}
$plugin = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin", 'bluepay_woocommerce_settings_link' );

/*Settings Link*/
