<?php
/*
   Plugin Name: eSewa Payment Gateway for WooCommerce
   Description: Extends WooCommerce with eSewa payment gateway
   Version: 1.0.2
   Plugin URI: https://wordpress.org/plugins/esewa-payment-gateway-for-woocommerce/
   Author: Nilambar Sharma
   Author URI: http://www.nilambar.net
   License: Under GPL2

*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

add_action('plugins_loaded', 'woocommerce_esewa_init', 0);

function woocommerce_esewa_init() {

   if ( !class_exists( 'WC_Payment_Gateway' ) )
      return;

   load_plugin_textdomain('esewa-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');

   /**
   * eSewa Payment Gateway class
   */
   class WC_Gateway_Esewa extends WC_Payment_Gateway
   {

    /**
     * Constructor for the gateway.
     *
     * @access public
     * @return void
     */
    public function __construct() {
  		global $woocommerce;
      $this->id                     = 'esewa';
      $this->method_title           = __( 'eSewa', 'esewa-woocommerce' );
      $this->method_description     = __( 'eSewa Description', 'esewa-woocommerce' );
      $this->icon                   = apply_filters('woocommerce_esewa_icon', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/images/esewa.png');
      $this->has_fields             = false;


      // Load the settings.
      $this->init_form_fields();
      $this->init_settings();

      // Define user set variables
      $this->title                = $this->get_option( 'title' );
      $this->description          = $this->get_option( 'description' );
      $this->liveurl              = 'http://esewa.com.np/epay/main';
      $this->testurl              = 'http://dev.esewa.com.np/epay/main';
      $this->liveurl_verification = 'http://esewa.com.np/epay/transrec';
      $this->testurl_verification = 'http://dev.esewa.com.np/epay/transrec';
      $this->merchant             = $this->get_option( 'merchant' );
      $this->testmode             = $this->get_option( 'testmode' );
      $this->debug                = $this->get_option( 'debug' );

      // Logs
      if ( 'yes' == $this->debug )
        $this->log = new WC_Logger();

      // Actions
      add_action( 'valid-esewa-standard-response', array( $this, 'successful_request' ) );
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
      add_action( 'woocommerce_receipt_esewa', array( $this, 'receipt_page' ) );
      add_action( 'woocommerce_thankyou_esewa', array( $this, 'thankyou_page' ) );


      // Customer Emails
      add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 2 );

      // Payment listener/API hook
      add_action( 'woocommerce_api_wc_gateway_esewa', array( $this, 'check_esewa_response' ) );


      if ( !$this->is_valid_for_use() ) $this->enabled = false;
    }



    /**
     * Initialise Gateway Settings Form Fields
     *
     * @access public
     * @return void
     */
    function init_form_fields() {

    	$this->form_fields = array(
			'enabled' => array(
							'title' => __( 'Enable/Disable', 'esewa-woocommerce' ),
							'type' => 'checkbox',
							'label' => __( 'Enable eSewa Payment Method', 'esewa-woocommerce' ),
							'default' => 'yes'
						),
			'title' => array(
							'title' => __( 'Title', 'esewa-woocommerce' ),
							'type' => 'text',
							'description' => __( 'This controls the title which the user sees during checkout.', 'esewa-woocommerce' ),
							'default' => __( 'eSewa', 'esewa-woocommerce' ),
							'desc_tip'      => true,
						),
			'description' => array(
							'title' => __( 'Customer Message', 'esewa-woocommerce' ),
							'type' => 'textarea',
							'description' => __( 'Enter description of payment gateway', 'esewa-woocommerce' ),
							'default' => __( 'eSewa is the first online payment gateway of Nepal. It facilitates its users to pay and get paid online.', 'esewa-woocommerce' )
						),
      'merchant' => array(
              'title' => __( 'Merchant ID', 'esewa-woocommerce' ),
              'type'      => 'text',
              'description' => __( 'Enter Merchant ID. Eg. 0000ETM', 'esewa-woocommerce' ),
              'default' => '',
              'placeholder' =>  __( 'Enter Merchant ID', 'esewa-woocommerce' ),
            ),
      'testing' => array(
              'title' => __( 'For Developers', 'esewa-woocommerce' ),
              'type' => 'title',
              'description' => '',
            ),
      'testmode' => array(
              'title' => __( 'Test mode', 'esewa-woocommerce' ),
              'type' => 'checkbox',
              'label' => __( 'Enable Test mode', 'esewa-woocommerce' ),
              'default' => 'no',
              'description' => __( 'Used for development purpose', 'esewa-woocommerce' ) ,
            ),
      'debug' => array(
              'title' => __( 'Debug Log', 'esewa-woocommerce' ),
              'type' => 'checkbox',
              'label' => __( 'Enable logging', 'esewa-woocommerce' ),
              'default' => 'no',
              'description' => sprintf( __( 'Log eSewa events, inside %swc-logs/esewa-%s.txt%s', 'esewa-woocommerce' ),
                '<code>',
                sanitize_file_name( wp_hash( 'esewa' ) ),'</code>'
                ),
            )
			);

    }

    /**
     * Check if this gateway is enabled and available in the user's country
     *
     * @access public
     * @return bool
     */
    function is_valid_for_use() {
        if ( ! in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_esewa_supported_currencies', array( 'NPR' ) ) ) ) return false;

        return true;
    }



  	/**
  	 * Admin Panel Options
  	 *
  	 * @access public
  	 * @return void
  	 */
  	public function admin_options() {
    	?>
    	<h3><?php _e( 'eSewa', 'esewa-woocommerce' ); ?></h3>
    	<p><?php _e('eSewa Payment Gateway', 'esewa-woocommerce' ); ?></p>

      <?php if ( $this->is_valid_for_use() ) : ?>

    	<table class="form-table">
      	<?php
      		// Generate the HTML For the settings form.
      		$this->generate_settings_html();
      	?>
  		</table><!--/.form-table-->
      <?php else : ?>
        <div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'esewa-woocommerce' ); ?></strong>: <?php _e( 'eSewa does not support your store currency.', 'esewa-woocommerce' ); ?></p></div>


    	<?php
      endif;

		}


    /**
     * Output for the order received page.
     *
     * @access public
     * @return void
     */
    function thankyou_page() {
		if ( $description = $this->get_description() )
        	echo wpautop( wptexturize( wp_kses_post( $description ) ) );
    }


    /**
     * Get eSewa Args for passing to the site
     *
     * @access public
     * @param mixed $order
     * @return array
     */
    function get_esewa_args( $order ) {
      global $woocommerce;

      // nspre($order,'order');

      if ( 'yes' == $this->debug )
        $this->log->add( 'esewa', 'Generating payment form for order ' . $order->get_order_number() );

      $order_id = $order->id;

      $order_total    = $order->get_total();
      $order_key      = $order->id;

      $esewa_args['tAmt']  = $order_total;
      $esewa_args['amt']   = $order_total;
      $esewa_args['txAmt'] = 0;
      $esewa_args['psc']   = 0;
      $esewa_args['pdc']   = 0;
      $esewa_args['scd']   = $this -> merchant ;
      $esewa_args['pid']   = $order_key ;
      $esewa_args['su']    = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_Gateway_Esewa', home_url( '/' ) ) );
      $myaccount_page_id = get_option( 'woocommerce_myaccount_page_id' );
      if ( $myaccount_page_id ) {
        $myaccount_page_url = get_permalink( $myaccount_page_id );
      }
      $esewa_args['fu']    = esc_url( $myaccount_page_url   ) ;


      $esewa_args = apply_filters( 'woocommerce_esewa_args', $esewa_args );

      // nspre($esewa_args);
      // die;

      return $esewa_args;
    }



    /**
     * Check for eSewa Response
     *
     * @access public
     * @return void
     */
    function check_esewa_response() {

      @ob_clean();

        if ( ! empty( $_REQUEST ) && $this->check_esewa_response_is_valid() ) {

            do_action( "valid-esewa-standard-response" );

      } else {

        wp_die( "eSewa Response Validation Failure" );

        }

    }

    /**
     * Check eSewa Response validity
     **/
    function check_esewa_response_is_valid() {
      global $woocommerce;

      if ( 'yes' == $this->debug )
        $this->log->add( 'esewa', 'Checking eSewa response is valid...' );

      if ( $this->testmode == 'yes' )
        $esewa_adr = $this->testurl_verification;
      else
        $esewa_adr = $this->liveurl_verification;

      $_REQUEST = stripslashes_deep( $_REQUEST );

      $params = array(
        'amt' => $_REQUEST['amt'],
        'pid' => $_REQUEST['oid'],
        'rid' => $_REQUEST['refId'],
        'scd' => $this -> merchant,
        );

        if ( 'yes' == $this->debug )
          $this->log->add( 'esewa', 'Request Parameters: ' . print_r( $params, true ) );

      $received_values = $params;

       $eparams = array(
        'body'      => $received_values,
        'sslverify'   => false,
        'timeout'     => 60,
        'httpversion'   => '1.1',
        'user-agent'  => 'WooCommerce/' . $woocommerce->version
       );
       $response = wp_remote_post( $esewa_adr, $eparams );

       if ( 'yes' == $this->debug )
        $this->log->add( 'esewa', 'eSewa Response: ' . print_r( $response, true ) );

      // check to see if the request was valid
      if ( ! is_wp_error( $response ) && $response['response']['code'] >= 200 && $response['response']['code'] < 300  ) {
          if ( 'yes' == $this->debug )
            $this->log->add( 'esewa', 'Received valid response from eSewa' );

          // Check response code
          $body = wp_remote_retrieve_body($response);
          $esewa_response_code = trim(strip_tags($body));
          if (!empty($esewa_response_code)) {
            $esewa_response_code = strtolower($esewa_response_code);
          }
          if ('success' == $esewa_response_code) {
            return true;
          }
          return false;
      }

      if ( 'yes' == $this->debug ) {
        $this->log->add( 'esewa', 'Received invalid response from eSewa' );
        if ( is_wp_error( $response ) )
          $this->log->add( 'esewa', 'Error response: ' . $response->get_error_message() );
      }

      return false;

    }


    /**
     * Successful Payment!
     *
     * @access public
     * @param array $posted
     * @return void
     */
    function successful_request(  ) {
      global $woocommerce;

      $_REQUEST = stripslashes_deep( $_REQUEST );

      $order = $this->get_esewa_order( $_REQUEST );

      if ($order) {

        if ( 'yes' == $this->debug )
          $this->log->add( 'esewa', 'Found order #' . $order->id );

        $order->payment_complete();
        $order->add_order_note(__('eSewa payment successful'), 'esewa-woocommerce' );
        $woocommerce->cart->empty_cart();

      }
      else{

        if ( 'yes' == $this->debug )
          $this->log->add( 'esewa', 'Order not found.-'. print_r($_REQUEST, true) );

        $order->update_status( 'on-hold', sprintf( __( 'Error occurred', 'esewa-woocommerce' ) ) );

      }

      if ( 'yes' == $this->debug )
        $this->log->add( 'esewa', 'Payment complete.' );

      $myaccount_page_id = get_option( 'woocommerce_myaccount_page_id' );
      if ( $myaccount_page_id ) {
        $myaccount_page_url = esc_url( get_permalink( $myaccount_page_id ) );
      }
      $redirect_url =  add_query_arg(array('q'=>'su', 'order' => $order->id ),$myaccount_page_url) ;
      wp_redirect( $redirect_url );
      exit;

    }

    /**
     * get_esewa_order function.
     *
     * @access public
     * @param mixed $posted
     * @return void
     */
    function get_esewa_order( $params ) {

      $order_id = $params['oid'];
      $order    = new WC_Order( $order_id );
      if (!empty($order)) {
        return $order;
      }
      if ( $this->debug=='yes' )
        $this->log->add( 'esewa', 'Error: Order Key does not match. #'. $params['oid'] );
      return false;
    }



    /**
     * Add content to the WC emails.
     *
     * @access public
     * @param WC_Order $order
     * @param bool $sent_to_admin
     * @return void
     */
    function email_instructions( $order, $sent_to_admin ) {

      if ( $sent_to_admin ) return;

      if ( $order->status !== 'on-hold') return;

      if ( $order->payment_method !== 'esewa') return;

      if ( $description = $this->get_description() )
        echo wpautop( wptexturize( $description ) );
    }


    /**
     * Generate the eSewa button link
     *
     * @access public
     * @param mixed $order_id
     * @return string
     */
    function generate_esewa_form( $order_id ) {
      global $woocommerce;

      $order = new WC_Order( $order_id );

      if ( $this->testmode == 'yes' ):
        $esewa_adr = $this->testurl . '?';
      else :
        $esewa_adr = $this->liveurl . '?';
      endif;

      $esewa_args = $this->get_esewa_args( $order );

      $esewa_args_array = array();

      foreach ($esewa_args as $key => $value) {
        $esewa_args_array[] = '<input type="hidden" name="'.esc_attr( $key ).'" value="'.esc_attr( $value ).'" />';
      }

      wc_enqueue_js( '
        jQuery("body").block({
            message: "' . esc_js( __( 'Thank you for your order. We are now redirecting you to eSewa to make payment.', 'esewa-woocommerce' ) ) . '",
            baseZ: 99999,
            overlayCSS:
            {
              background: "#fff",
              opacity: 0.6
            },
            css: {
                  padding:        "20px",
                  zindex:         "9999999",
                  textAlign:      "center",
                  color:          "#555",
                  border:         "3px solid #aaa",
                  backgroundColor:"#fff",
                  cursor:         "wait",
                  lineHeight:   "24px",
              }
          });
        jQuery("#submit_esewa_payment_form").click();
      ' );

      return '<form action="'.esc_url( $esewa_adr ).'" method="post" id="esewa_payment_form" target="_top">
          ' . implode( '', $esewa_args_array) . '
          <input type="submit" class="button alt" id="submit_esewa_payment_form" value="' . __( 'Pay via eSewa', 'esewa-woocommerce' ) . '" /> <a class="button cancel" href="'.esc_url( $order->get_cancel_order_url() ).'">'.__( 'Cancel order &amp; restore cart', 'esewa-woocommerce' ).'</a>
        </form>';

    }
    /**
     * Process the payment and return the result
     *
     * @access public
     * @param int $order_id
     * @return array
     */
    function process_payment( $order_id ) {

      global $woocommerce;

      if ($this -> merchant == '') {
        $woocommerce -> add_error(__( 'eSewa is not setup correctly. Cannot proceed !', 'esewa-woocommerce' ));
        if ( $this->debug=='yes' )
          $this->log->add( 'esewa', 'Merchant ID is empty.' );
        return ;
      }

      $order = new WC_Order( $order_id );

      $esewa_args = $this->get_esewa_args( $order );

      if ( $this->testmode == 'yes' ):
        $esewa_adr = $this->testurl ;
      else :
        $esewa_adr = $this->liveurl ;
      endif;

      $esewa_args_array = array();
      foreach($esewa_args as $key => $value){
        $esewa_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
      }

      return array(
        'result'  => 'success',
        'redirect'  => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay' ))))
      );

    }
    /**
     * Output for the order received page.
     *
     * @access public
     * @return void
     */
    function receipt_page( $order ) {

      echo '<p>'.__( 'Thank you for your order, please click the button below to pay with eSewa.', 'esewa-woocommerce' ).'</p>';

      echo $this->generate_esewa_form( $order );

    }

} // end Class




  /**
  * Add this Gateway to WooCommerce
  **/
   function woocommerce_add_esewa_gateway($methods)
   {
      $methods[] = 'WC_Gateway_Esewa';
      return $methods;
   }

   add_filter('woocommerce_payment_gateways', 'woocommerce_add_esewa_gateway' );
}


  /**
   * Show Messages
   *
   * @access public
   * @return void
   */
  function esewa_message( $order ) {
    global $woocommerce;

    $_REQUEST = stripslashes_deep( $_REQUEST );

    if (isset($_REQUEST['q'])) {
      if ('fu' == $_REQUEST['q'] ) {

        wc_add_notice(__( 'Payment could not be completed!', 'esewa-woocommerce' ), 'error' );

      }
      if ('su' == $_REQUEST['q'] ) {
        $msg_content = __( 'Payment Successful!', 'esewa-woocommerce' );
        if ( isset($_REQUEST['order']) && '' != $_REQUEST['order']   )  {
          $view_url = esc_url( add_query_arg('order', $_REQUEST['order'], get_permalink( woocommerce_get_page_id( 'view_order' ) ) ) );
          $msg_content .= '<a href="'.$view_url.'" class="button">' ;
          $msg_content .= __( 'View Order', 'esewa-woocommerce' );
          $msg_content .= '</a>' ;
        }
        wc_add_notice( $msg_content, 'success' );
      }
    }

  }
  add_action( 'woocommerce_init',  'esewa_message' ) ;
