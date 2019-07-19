<?php

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}


GFForms::include_feed_addon_framework();


/**
 * Gravity Forms MailWizz Add-On.
 *
 * @since     1.0
 * @package   GravityForms
 * @author    TunisianCloud
 * @copyright Copyright (c) 2019, TunisianCloud
 */
class GFMailWizz extends GFFeedAddOn
 {

    protected $_version = '1.0';
    protected $_min_gravityforms_version = '1.9';
    protected $_slug = 'mailwizz';
    protected $_path = 'mailwizz/mailwizz.php';
    protected $_full_path = __FILE__;
    protected $_title = 'GF MailWizz Add-On';
	protected $_short_title = 'MailWizz';
	
    /**
	 * Contains an instance of the MailWizz API library, if available.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    GF_MailWizz_API $api If available, contains an instance of the MailWizz API library.
	 */
	public $api = null;

    
    private static $_instance = null;

    public static function get_instance() {
        if ( self::$_instance == null ) {
            self::$_instance = new self();
        }
     
        return self::$_instance;
    }

    public function init() {
        parent::init();
    }

	// # PLUGIN SETTINGS -----------------------------------------------------------------------------------------------

	/**
	 * Configures the settings which should be rendered on the add-on settings tab.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return array
	 */
    public function plugin_settings_fields() {

		return array(
			array(
				'description' => '<p>' .
					sprintf(
						esc_html__( 'MailWizz makes it easy to send email newsletters to your customers, manage your subscriber lists, and track campaign performance. Use Gravity Forms to collect customer information and automatically add it to your MailWizz subscriber list. If you don\'t have a MailWizz account, you can %1$ssign up for one here.%2$s', 'mailwizz' ),
						'<a href="http://www.tunisiancloud.com/" target="_blank">', '</a>'
					)
					. '</p>',
				'fields'      => array(
					array(
						'name'              => 'apiKey',
						'label'             => esc_html__( 'MailWizz API Key', 'mailwizz' ),
						'type'              => 'text',
						'class'             => 'medium',
						'feedback_callback' => array( $this, 'initialize_api' ),
					),
				),
			),
		);

	}

	// # FEED SETTINGS -------------------------------------------------------------------------------------------------

	/**
	 * Configures the settings which should be rendered on the feed edit page.
	 *
	 * @since  3.0
	 * @access public
	 *
	 * @return array
	 */
	public function feed_settings_fields() {
		return array(
			array(
				'title'  => esc_html__( 'MailWizz Feed Settings', 'mailwizz' ),
				'fields' => array(
					array(
						'label'   => esc_html__( 'Feed name', 'mailwizz' ),
						'type'    => 'text',
						'name'    => 'feedName',
						'tooltip' => esc_html__( 'Enter the name for your new feed', 'mailwizz' ),
						'class'   => 'meduim',
                    ),
                    array(
						'name'     => 'mailwizzList',
						'label'    => esc_html__( 'MailWizz List', 'mailwizz' ),
						'type'     => 'mailwizz_list',
						'required' => true,
						'tooltip'  => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'MailWizz List', 'mailwizz' ),
							esc_html__( 'Select the MailWizz list you would like to add your contacts to.', 'mailwizz' )
						),
					),
				),
			),
			array(
				'dependency' => 'mailwizzList',
				'fields'     => array(
					array(
						'name'      => 'mappedFields',
						'label'     => esc_html__( 'Map Fields', 'mailwizz' ),
						'type'      => 'field_map',
						'field_map' => $this->merge_vars_field_map(),
						'tooltip'   => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Map Fields', 'mailwizz' ),
							esc_html__( 'Associate your MailChimp merge tags to the appropriate Gravity Form fields by selecting the appropriate form field from the list.', 'mailwizz' )
						),
					),
					array(
						'name'    => 'options',
						'label'   => esc_html__( 'Options', 'mailwizz' ),
						'type'    => 'checkbox',
						'choices' => array(
							array(
								'name'          => 'double_optin',
								'label'         => esc_html__( 'Double Opt-In', 'mailwizz' ),
								'default_value' => 1,
								'onclick'       => 'if(this.checked){jQuery("#mailwizz_doubleoptin_warning").hide();} else{jQuery("#mailwizz_doubleoptin_warning").show();}',
								'tooltip'       => sprintf(
									'<h6>%s</h6>%s',
									esc_html__( 'Double Opt-In', 'mailwizz' ),
									esc_html__( 'When the double opt-in option is enabled, MailChimp will send a confirmation email to the user and will only add them to your MailChimp list upon confirmation.', 'mailwizz' )
								),
							),
						),
					),
					array(
						'name'    => 'optinCondition',
						'label'   => esc_html__( 'Conditional Logic', 'mailwizz' ),
						'type'    => 'feed_condition',
						'tooltip' => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Conditional Logic', 'mailwizz' ),
							esc_html__( 'When conditional logic is enabled, form submissions will only be exported to MailChimp when the conditions are met. When disabled all form submissions will be exported.', 'mailwizz' )
						),
					),
					array( 'type' => 'save' ),
				),
			),		
		);
	}

	/**
	 * Define the markup for the mailwizz_list type field.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $field The field properties.
	 * @param bool  $echo  Should the setting markup be echoed. Defaults to true.
	 *
	 * @return string
	 */
    public function settings_mailwizz_list( $field, $echo = true ) {
		// Initialize HTML string.
		$html = '';

		// If API is not initialized, return.
		if ( ! $this->initialize_api() ) {
			return $html;
		}
		try {

			// Log contact lists request parameters.
			$this->log_debug( __METHOD__ . '(): Retrieving contact lists; params: ' . print_r( 'params', true ) );
			// Get lists.
			$lists = $this->get_lists();
	
			$count = 0;
			foreach($lists as $list){
					$count++;	
			}

		} catch ( Exception $e ) {

			// Log that contact lists could not be obtained.
			$this->log_error( __METHOD__ . '(): Could not retrieve MailWizz contact lists; ' . $e->getMessage() );

			// Display error message.
			printf( esc_html__( 'Could not load MailWizz contact lists. %sError: %s', 'mailwizz' ), '<br/>', $e->getMessage() );

			return;

		}

		// If no lists were found, display error message.
		if ( $count == 0) {

			// Log that no lists were found.
			$this->log_error( __METHOD__ . '(): Could not load MailWizz contact lists; no lists found.' );

			// Display error message.
			printf( esc_html__( 'Could not load MailWizz contact lists. %sError: %s', 'mailwizz' ), '<br/>', esc_html__( 'No lists found.', 'mailwizz' ) );

			return;

		}

		// Log number of lists retrieved.
		$this->log_debug( __METHOD__ . '(): Number of lists: ' .$count );

		// Initialize select options.
		$options = array(
			array(
				'label' => esc_html__( 'Select a MailWizz List', 'mailwizz' ),
				'value' => '',
			),
		);

		// Loop through MailWizz lists.
		foreach ( $lists as $list ) {

			// Add list to select options.
			$options[] = array(
				'label' => esc_html( $list['name'] ),
				'value' => esc_attr( $list['uid'] ),
			);

		}

		// Add select field properties.
		$field['type']     = 'select';
		$field['choices']  = $options;
		$field['onchange'] = 'jQuery(this).parents("form").submit();';

		// Generate select field.
		$html = $this->settings_select( $field, false );

		if ( $echo ) {
			echo $html;
		}
		
		return $html;

	}

	/**
	 * Return an array of MailWizz list fields which can be mapped to the Form fields/entry meta.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return array
	 */
	public function merge_vars_field_map() {

		// Initialize field map array.
		$field_map = array(
			'EMAIL' => array(
				'name'       => 'EMAIL',
				'label'      => esc_html__( 'Email Address', 'mailwizz' ),
				'required'   => true,
				'field_type' => array( 'email', 'hidden' ),
			),
		);

		// If unable to initialize API, return field map.
		if ( ! $this->initialize_api() ) {
			return $field_map;
		}

		// Get current list ID.
		$list_id = $this->get_setting( 'mailwizzList' );

		// Get merge fields.
		$merge_fields = $this->get_list_merge_fields( $list_id );

		// If merge fields exist, add to field map.
		if ( ! empty( $merge_fields['list']['fields'] ) ) {

			// Loop through merge fields.
			foreach ( $merge_fields['list']['fields'] as $merge_field ) {
			
				// Define required field type.
				$field_type = null;

				// If this is an email merge field, set field types to "email" or "hidden".
				if ( 'EMAIL' === strtoupper( $merge_field['tag'] ) ) {
					$field_type = array( 'email', 'hidden' );
				}

				// If this is an address merge field, set field type to "address".
				if ( 'address' === $merge_field['tag'] ) {
					$field_type = array( 'address' );
				}

				// Add to field map.
				$field_map[ $merge_field['tag'] ] = array(
					'name'       => $merge_field['tag'],
					'label'      => $merge_field['label'],
					'required'   => $merge_field['required'],
					'field_type' => $field_type,
				);

			}

		}

		return $field_map;
	}

	/**
	 * Prevent feeds being listed or created if the API key isn't valid.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return bool
	 */
	public function can_create_feed() {

		return $this->initialize_api();

	}

	/**
	 * Configures which columns should be displayed on the feed list page.
	 *
	 * @since  3.0
	 * @access public
	 *
	 * @return array
	 */
	public function feed_list_columns() {

		return array(
			'feedName'            => esc_html__( 'Name', 'mailwizz' ),
			'mailwizz_list_name' => esc_html__( 'MailWizz List', 'mailwizz' ),
		);

	}
	
	/**
	 * Returns the value to be displayed in the MailWizz List column.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $feed The feed being included in the feed list.
	 *
	 * @return string
	 */
	public function get_column_value_mailwizz_list_name( $feed ) {

		// If unable to initialize API, return the list ID.
		if ( ! $this->initialize_api() ) {
			return rgars( $feed, 'meta/mailwizzList' );
		}

		try {

			// Get list.
			$list = $this->get_list( rgars( $feed, 'meta/mailwizzList' ) );
			
			return rgar( $list['list'], 'name' );

		} catch ( Exception $e ) {

			// Log error.
			$this->log_error( __METHOD__ . '(): Unable to get MailWizz list for feed list; ' . $e->getMessage() );

			// Return list ID.
			return rgars( $feed, 'meta/mailwizzList' );

		}

	}

	/**
	 * Define the markup for the double_optin checkbox input.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array  $choice     The choice properties.
	 * @param string $attributes The attributes for the input tag.
	 * @param string $value      Is choice selected (1 if field has been checked. 0 or null otherwise).
	 * @param string $tooltip    The tooltip for this checkbox item.
	 *
	 * @return string
	 */
	public function checkbox_input_double_optin( $choice, $attributes, $value, $tooltip ) {

		// Get checkbox input markup.
		$markup = $this->checkbox_input( $choice, $attributes, $value, $tooltip );

		// Define visibility status of warning.
		$display = $value ? 'none' : 'block-inline';

		// Add warning to checkbox markup.
		$markup .= '<span id="mailwizz_doubleoptin_warning" style="padding-left: 10px; font-size: 10px; display:' . $display . '">(' . esc_html__( 'Abusing this may cause your MailWizz account to be suspended.', 'mailwizz' ) . ')</span>';

		return $markup;

	}

	
	// # Request Methods-------------------------------------------------------------------------------------

	/**
	 * Get a specific MailWizz list.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param string $list_id       MailWizz list ID.
	 *
	 * @return array
	 * @throws GF_MailChimp_Exception|Exception
	 */

	public function get_list( $list_id ) {

		return $this->process_request( 'lists/' . $list_id );

	}

	/**
	 * Get all merge fields for a MailWizz list.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param string $list_id MailWizz list ID.
	 *
	 * @uses   GF_MailWizz::process_request()
	 *
	 * @return array
	 * @throws GF_MailWizz_Exception|Exception
	 */
	public function get_list_merge_fields( $list_id ) {

		return $this->process_request( 'lists/' . $list_id );

	}

	/**
	 * Get all MailWizz lists.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $params List request parameters.
	 *
	 * @return array
	 * @throws GF_MailWizz_Exception|Exception
	 */
	public function get_lists() {

		return $this->process_request( 'lists' );

	}

	/**
	 * Get a specific MailWizz list member.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param string $list_id       MailWizzlist ID.
	 * @param string $email_address Email address.
	 *
	 * @return array
	 * @throws GF_MailWizz_Exception|Exception
	 */
	public function get_list_member( $list_id, $email_address ) {


		return $this->process_request( 'lists/' . $list_id . '/subscribers/' . $email_address );

	}

	/**
	 * Add a specific member to MailWizz list.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param string $list_id       MailWizz list ID.
	 * @param array $subscription  args to be updated
	 *
	 * @return array
	 * @throws GF_MailWizz_Exception|Exception
	 */
	public function add_list_member( $list_id, $subscription) {

		return $this->process_request( 'lists/' . $list_id . '/subscribers/store' , $subscription,'POST');

	}
	/**
	 * Update a specific MailWizz list member.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param string $list_id       MailWizz list ID.
	 * @param array $subscription  args to be updated
	 *
	 * @return array
	 * @throws GF_MailWizz_Exception|Exception
	 */
	public function update_list_member( $list_id, $subscription) {

		return $this->process_request( 'lists/' . $list_id . '/subscribers/'. $subscription['uid'] .'/update' , $subscription,'PATCH');

	}

	

	// # Processing Requests---------------------------------------------------------------------------------------------

	/**
	 * Process MailWizz API request.
	 *
	 * @since  1.0
	 * @access private
	 *
	 * @param string $path       Request path.
	 * @param array  $data       Request data.
	 * @param string $method     Request method. Defaults to GET.
	 * @param string $return_key Array key from response to return. Defaults to null (return full response).
	 *
	 * @throws GF_MailWizz_Exception|Exception If API request returns an error, exception is thrown.
	 *
	 * @return array
	 */

	private function process_request( $path = '', $data = [], $method = 'GET', $return_key = null ) {

		// If API key is not set, throw exception.
		if ( rgblank( $this->get_plugin_setting( 'apiKey' ) ) ) {
			throw new Exception( 'API key must be defined to process an API request.' );
		}

		// Build base request URL.
		$request_url = 'https://mailing.tunisiancloud.com/api/v1/' . $path. '?api_token=' . $this->get_plugin_setting('apiKey');

		
		// Add request URL parameters if needed.
		if ( 'PATCH' === $method ||'POST' === $method && ! empty( $data['merge_vars'] ) ) {
			
			if(! empty($data['EMAIL_ADDRESS'])){

				$request_url = $request_url.'&EMAIL='.$data['EMAIL_ADDRESS'];
				
				foreach($data['merge_vars'] as  $key => $value){
					if(! empty ($value)){
	
						$request_url = $request_url.'&'.$key.'='.$value;
					
					}
				}
			}
		}
		
		// Build base request arguments.
		$args = array(
			'method'   => $method,
			'headers'  => array(
				'Accept'        => 'application/json',
				'Authorization' => 'Basic ' . base64_encode( ':' . $this->get_plugin_setting('apiKey') ),
				'Content-Type'  => 'application/json',
			),
			/**
			 * Filters if SSL verification should occur.
			 *
			 * @param bool false If the SSL certificate should be verified. Defalts to false.
			 *
			 * @return bool
			 */
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			/**
			 * Sets the HTTP timeout, in seconds, for the request.
			 *
			 * @param int 30 The timeout limit, in seconds. Defalts to 30.
			 *
			 * @return int
			 */
			'timeout'   => apply_filters( 'http_request_timeout', 30 ),
		);

		// Add data to arguments if needed.
		if ( 'GET' !== $method ) {
			$args['body'] = json_encode( $data );
		}

		/**
		 * Filters the MailWizz request arguments.
		 *
		 * @param array  $args The request arguments sent to MailWizz.
		 * @param string $path The request path.
		 *
		 * @return array
		 */
		$args = apply_filters( 'gform_mailchimp_request_args', $args, $path );

		// Get request response.
		$response = wp_remote_request( $request_url, $args );
		
		// If request was not successful, throw exception.
		if ( is_wp_error( $response ) ) {
			throw new GF_MailWizz_Exception( $response->get_error_message() );
		}

		// Decode response body.
		$response['body'] = json_decode( $response['body'], true );

		// Get the response code.
		$response_code = wp_remote_retrieve_response_code( $response );

		if ( ! in_array( $response_code, array( 200, 204 ) ) ) {

			// If status code is set, throw exception.
			if ( isset( $response['body']['status'] ) && isset( $response['body']['title'] ) ) {

				// Initialize exception.
				$exception = new GF_MailWizz_Exception( $response['body']['title'], $response['body']['status'] );

				// Add detail.
				$exception->setDetail( $response['body']['detail'] );

				// Add errors if available.
				if ( isset( $response['body']['errors'] ) ) {
					$exception->setErrors( $response['body']['errors'] );
				}

				throw $exception;

			}

			throw new GF_MailWizz_Exception( wp_remote_retrieve_response_message( $response ), $response_code );

		}

		// Remove links from response.
		unset( $response['body']['_links'] );

		// If a return key is defined and array item exists, return it.
		if ( ! empty( $return_key ) && isset( $response['body'][ $return_key ] ) ) {
			return $response['body'][ $return_key ];
		}

		return $response['body'];

	}

	// # FEED PROCESSING-------------------------------------------------------------------------------------------

	/**
	 * Process the feed, subscribe the user to the list.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $feed  The feed object to be processed.
	 * @param array $entry The entry object currently being processed.
	 * @param array $form  The form object currently being processed.
	 *
	 * @return array
	 */
	public function process_feed( $feed, $entry, $form ) {

		// Log that we are processing feed.
		$this->log_debug( __METHOD__ . '(): Processing feed.' );

		// If unable to initialize API, log error and return.
		if ( ! $this->initialize_api() ) {
			$this->add_feed_error( esc_html__( 'Unable to process feed because API could not be initialized.', 'mailwizz' ), $feed, $entry, $form );
			return $entry;
		}

		// Get field map values.
		$field_map = $this->get_field_map_fields( $feed, 'mappedFields' );
		
		// Get mapped email address.
		$email = $this->get_field_value( $form, $entry, $field_map['EMAIL'] );
	

		// If email address is invalid, log error and return.
		if ( GFCommon::is_invalid_or_empty_email( $email ) ) {
			$this->add_feed_error( esc_html__( 'A valid Email address must be provided.', 'mailwizz' ), $feed, $entry, $form );
			return $entry;
		}

		/**
		 * Prevent empty form fields erasing values already stored in the mapped MailChimp MMERGE fields
		 * when updating an existing subscriber.
		 *
		 * @param bool  $override If the merge field should be overridden.
		 * @param array $form     The form object.
		 * @param array $entry    The entry object.
		 * @param array $feed     The feed object.
		 */
		$override_empty_fields = gf_apply_filters( 'gform_mailchimp_override_empty_fields', array( $form['id'] ), true, $form, $entry, $feed );

		// Log that empty fields will not be overridden.
		if ( ! $override_empty_fields ) {
			$this->log_debug( __METHOD__ . '(): Empty fields will not be overridden.' );
		}

		// Initialize array to store merge vars.
		$merge_vars = array();

		// Loop through field map.
		foreach ( $field_map as $name => $field_id ) {

			// If no field is mapped, skip it.
			if ( rgblank( $field_id ) ) {
				continue;
			}

			// If this is the email field, skip it.
			if ( strtoupper( $name ) === 'EMAIL' ) {
				continue;
			}

			// Set merge var name to current field map name.
			$this->merge_var_name = $name;

			// Get field object.
			$field = GFFormsModel::get_field( $form, $field_id );

			// Get field value.
			$field_value = $this->get_field_value( $form, $entry, $field_id );

			// If field value is empty and we are not overriding empty fields, skip it.
			if ( empty( $field_value ) && ( ! $override_empty_fields || ( is_object( $field ) && 'address' === $field->get_input_type() ) ) ) {
				continue;
			}

			$merge_vars[ $name ] = $field_value;


		}

		// Define initial member, member found and member status variables.
		$member        = false;
		$member_found  = false;
		$member_status = null;

		try {

			// Log that we are checking if user is already subscribed to list.
			$this->log_debug( __METHOD__ . "(): Checking to see if $email is already on the list." );

			// Get member info.
			$member = $this->get_list_member( $feed['meta']['mailwizzList'], $email );


			// Set member found status to true.
			$member_found = true;

			// Set member status.
			$member_status = $member['subscriber']['status'];

			// Log member status.
			$this->log_debug( __METHOD__ . "(): $email was found on list. Status: $member_status" );

		} catch ( Exception $e ) {

			// If the exception code is not 404, abort feed processing.
			if ( 404 !== $e->getCode() ) {

				// Log that we could not get the member information.
				$this->add_feed_error( sprintf( esc_html__( 'Unable to check if email address is already used by a member: %s', 'mailwizz' ), $e->getMessage() ), $feed, $entry, $form );

				return $entry;

			}

			// Log member status.
			$this->log_debug( __METHOD__ . "(): $email was not found on list." );

		}

		
		/**
		 * Modify whether a user that currently has a status of unsubscribed on your list is resubscribed.
		 * By default, the user is resubscribed.
		 *
		 * @param bool  $allow_resubscription If the user should be resubscribed.
		 * @param array $form                 The form object.
		 * @param array $entry                The entry object.
		 * @param array $feed                 The feed object.
		 */
		$allow_resubscription = gf_apply_filters( array( 'gform_mailchimp_allow_resubscription', $form['id'] ), true, $form, $entry, $feed );

		// If member is unsubscribed and resubscription is not allowed, exit.
		if ( 'unsubscribed' == $member_status && ! $allow_resubscription ) {
			$this->log_debug( __METHOD__ . '(): User is unsubscribed and resubscription is not allowed.' );
			return;
		}

		// If member status is not defined or is anything other than pending, set to subscribed.
		$member_status = isset( $member_status ) && $member_status === 'pending' ? $member_status : 'subscribed';
		

		// Prepare subscription arguments.
		$subscription = array(
			'id'           => $feed['meta']['mailwizzList'],
			'uid'		   => $member['subscriber']['uid'],
			'email'        => array( 'email' => $email ),
			'merge_vars' => $merge_vars,
		);

		// Prepare transaction type for filter.
		$transaction = $member_found ? 'Update' : 'Subscribe';

		/**
		 * Modify the subscription object before it is executed.
		 *
		 * @deprecated 4.0 @use gform_mailchimp_subscription
		 *
		 * @param array  $subscription Subscription arguments.
		 * @param array  $form         The form object.
		 * @param array  $entry        The entry object.
		 * @param array  $feed         The feed object.
		 * @param string $transaction  Transaction type. Defaults to Subscribe.
		 */
		$subscription = gf_apply_filters( array( 'gform_mailchimp_args_pre_subscribe', $form['id'] ), $subscription, $form, $entry, $feed, $transaction );


		// Extract list ID.
		$list_id = $subscription['id'];
		unset( $subscription['id'] );

		// Convert email address.
		$subscription['EMAIL_ADDRESS'] = $subscription['email']['email'];
		unset( $subscription['email'] );

		/**
		 * Modify the subscription object before it is executed.
		 *
		 * @since 4.1.9 Added existing member object as $member parameter.
		 *
		 * @param array       $subscription Subscription arguments.
		 * @param string      $list_id      MailChimp list ID.
		 * @param array       $form         The form object.
		 * @param array       $entry        The entry object.
		 * @param array       $feed         The feed object.
		 * @param array|false $member       The existing member object. (False if member does not currently exist in MailChimp.)
		 */
		$subscription = gf_apply_filters( array( 'gform_mailchimp_subscription', $form['id'] ), $subscription, $list_id, $form, $entry, $feed, $member );


		$action = $member_found ? 'updated' : 'added';

		try {

			// Log the subscriber to be added or updated.
			$this->log_debug( __METHOD__ . "(): Subscriber to be {$action}: " . print_r( $subscription, true ) );

			// Add or update subscriber.
			
			if ( false == $member_found ) {

				$this->add_list_member($list_id, $subscription);
				
			}else{

				$this->update_list_member( $list_id, $subscription );

			}

			// Log that the subscription was added or updated.
			$this->log_debug( __METHOD__ . "(): Subscriber successfully {$action}." );

		} catch ( Exception $e ) {

			// Log that subscription could not be added or updated.
			$this->add_feed_error( sprintf( esc_html__( 'Unable to add/update subscriber: %s', 'mailwizz' ), $e->getMessage() ), $feed, $entry, $form );

			// Log field errors.
			if ( $e->hasErrors() ) {
				$this->log_error( __METHOD__ . '(): Field errors when attempting subscription: ' . print_r( $e->getErrors(), true ) );
			}

			return $entry;

		}

	}

	// # HELPERS -------------------------------------------------------------------------------------------------------

	/**
	 * Initializes MailChimp API if credentials are valid.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param string $api_key MailWizz API key.
	 *
	 * @uses GFAddOn::get_plugin_setting()
	 * @uses GFAddOn::log_debug()
	 * @uses GFAddOn::log_error()
	 *
	 * @return bool|null
	 */
	public function initialize_api( $api_key = null ) {

		// If API is already initialized, return true.
		if ( ! is_null( $this->api ) ) {
			return true;
		}

		// Get the API key.
		if ( rgblank( $api_key ) ) {
			$api_key = $this->get_plugin_setting( 'apiKey' );
		}

		// If the API key is blank, do not run a validation check.
		if ( rgblank( $api_key ) ) {
			return null;
		}

		// Log validation step.
		$this->log_debug( __METHOD__ . '(): Validating API Info.' );
		return true;
	}

    
}

/**
 * Gravity Forms MailWizz Exception.
 *
 * @since     1.0.0
 * @package   GravityForms
 * @author    TunisianCloud
 * @copyright Copyright (c) 2019, TunisianCloud
 */
class GF_MailWizz_Exception extends Exception {

	/**
	 * Additional details about the exception.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string $detail Additional details about the exception.
	 */
	protected $detail;

	/**
	 * Exception error messages.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    array $errors Exception error messages.
	 */
	protected $errors;

	/**
	 * Get additional details about the exception.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return string|null
	 */
	public function getDetail() {

		return $this->detail;

	}

	/**
	 * Get exception error messages.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return array|null
	 */
	public function getErrors() {

		return $this->errors;

	}

	/**
	 * Determine if exception has additional details.
	 *
	 * @since  4.1.11
	 * @access public
	 *
	 * @return bool
	 */
	public function hasDetail() {

		return ! empty( $this->detail );

	}

	/**
	 * Determine if exception has error messages.
	 *
	 * @since  4.1.11
	 * @access public
	 *
	 * @return bool
	 */
	public function hasErrors() {

		return ! empty( $this->errors );

	}

	/**
	 * Set exception details.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param string $detail Additional details about the exception.
	 */
	public function setDetail( $detail ) {

		$this->detail = $detail;

	}

	/**
	 * Set exception error messages.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param string $detail Additional error messages about the exception.
	 */
	public function setErrors( $errors ) {

		$this->errors = $errors;

	}

}