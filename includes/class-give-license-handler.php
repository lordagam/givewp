<?php
/**
 * Give License handler
 *
 * This class simplifies the process of adding license information to new Give add-ons.
 *
 * @version 1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Give_License' ) ) :

	/**
	 * Give_License Class
	 */
	class Give_License {

		/**
		 * File
		 *
		 * @access private
		 *
		 * @var    string
		 */
		private $file;

		/**
		 * License
		 *
		 * @access private
		 *
		 * @var    string
		 */
		private $license;

		/**
		 * Item name
		 *
		 * @access private
		 *
		 * @var    string
		 */
		private $item_name;

		private $license_data;

		/**
		 * Item shortname
		 *
		 * @access private
		 *
		 * @var    string
		 */
		private $item_shortname;

		/**
		 * Version
		 *
		 * @access private
		 *
		 * @var    string
		 */
		private $version;

		/**
		 * Author
		 *
		 * @access private
		 *
		 * @var    string
		 */
		private $author;

		/**
		 * API URL
		 *
		 * @access private
		 *
		 * @var    string
		 */
		private $api_url      = 'http://give-playground.dev/give-sl-api/';
		private $account_url  = 'http://give-playground.dev/my-account/';
		private $checkout_url = 'http://give-playground.dev/checkout/';

		/**
		 * Class Constructor
		 *
		 * Set up the Give License Class.
		 *
		 * @access public
		 * @param string  $_file
		 * @param string  $_item_name
		 * @param string  $_version
		 * @param string  $_author
		 * @param string  $_optname
		 * @param string  $_api_url
		 * @param string  $_checkout_url
		 * @param string  $_account_url
		 */
		public function __construct( $_file, $_item_name, $_version, $_author, $_optname = null, $_api_url = null, $_checkout_url = null, $_account_url = null ) {
			global $give_options;

			$this->file           = $_file;
			$this->item_name      = $_item_name;
			$this->item_shortname = 'give_' . preg_replace( '/[^a-zA-Z0-9_\s]/', '', str_replace( ' ', '_', strtolower( $this->item_name ) ) );
			$this->version        = $_version;
			$this->license        = isset( $give_options[ $this->item_shortname . '_license_key' ] ) ? trim( $give_options[ $this->item_shortname . '_license_key' ] ) : '';
			$this->license_data   = get_option( $this->item_shortname . '_license_active' );
            $this->author         = $_author;
			$this->api_url        = is_null( $_api_url ) ? $this->api_url : $_api_url;
			$this->checkout_url   = is_null( $_checkout_url ) ? $this->checkout_url : $_checkout_url;
			$this->account_url    = is_null( $_account_url ) ? $this->account_url : $_account_url;

			// Setup hooks
			$this->includes();
			$this->hooks();
			//$this->auto_updater();
		}

		/**
		 * Includes
		 *
		 * Include the updater class.
		 *
		 * @access private
		 *
		 * @return void
		 */
		private function includes() {
			if ( ! class_exists( 'EDD_SL_Plugin_Updater' ) ) {
				require_once 'admin/EDD_SL_Plugin_Updater.php';
			}
		}

		/**
		 * Hooks
		 *
		 * Setup license hooks.
		 *
		 * @access private
		 *
		 * @return void
		 */
		private function hooks() {

			// Register settings
			add_filter( 'give_settings_licenses', array( $this, 'settings' ), 1 );

			// Activate license key on settings save
			add_action( 'admin_init', array( $this, 'activate_license' ) );

			// Deactivate license key
			add_action( 'admin_init', array( $this, 'deactivate_license' ) );

			// Updater
			add_action( 'admin_init', array( $this, 'auto_updater' ), 0 );

			add_action( 'admin_notices', array( $this, 'notices' ) );

            // Check license weekly.
            add_action( 'give_weekly_scheduled_events', array( $this, 'weekly_license_check' ) );

			// Check subscription weekly.
			add_action( 'give_weekly_scheduled_events', array( $this, 'weekly_subscription_check' ) );
        }

		/**
		 * Auto Updater
		 *
		 * @access  private
		 * @global  array $give_options
		 * @return  bool
		 */
		public function auto_updater() {

			if ( ! $this->is_valid_license() ) {
				return false;
			}

			// Setup the updater
			$give_updater = new EDD_SL_Plugin_Updater(
				$this->api_url,
				$this->file,
				array(
					'version'   => $this->version,
					'license'   => $this->license,
					'item_name' => $this->item_name,
					'author'    => $this->author
				)
			);
		}

		/**
		 * License Settings
		 *
		 * Add license field to settings.
		 *
		 * @access public
		 *
		 * @param  array $settings License settings.
		 *
		 * @return array           License settings.
		 */
		public function settings( $settings ) {

			$give_license_settings = array(
				array(
					'name'    => $this->item_name,
					'id'      => $this->item_shortname . '_license_key',
					'desc'    => '',
					'type'    => 'license_key',
					'options' => array(
					    'license'       => get_option( $this->item_shortname . '_license_active' ),
                        'shortname'     => $this->item_shortname,
                        'item_name'     => $this->item_name,
                        'api_url'       => $this->api_url,
                        'checkout_url'  => $this->checkout_url,
                        'account_url'   => $this->account_url
                    ),
					'size'    => 'regular'
				)
			);

			return array_merge( $settings, $give_license_settings );
		}

		/**
		 * License Settings Content
		 *
		 * Add Some Content to the Licensing Settings.
		 *
		 * @access public
		 *
		 * @param  array $settings License settings content.
		 *
		 * @return array           License settings content.
		 */
		public function license_settings_content( $settings ) {

			$give_license_settings = array(
				array(
					'name' => esc_html__( 'Add-on Licenses', 'give' ),
					'desc' => '<hr>',
					'type' => 'give_title',
					'id'   => 'give_title'
				),
			);

			return array_merge( $settings, $give_license_settings );
		}

		/**
		 * Activate License
		 *
		 * Activate the license key.
		 *
		 * @access public
		 *
		 * @return void
		 */
		public function activate_license() {
            // Bailout: Check if license key set of not.
			if ( ! isset( $_POST[ $this->item_shortname . '_license_key' ] ) ) {
				return;
			}

			// Security check.
			if ( ! wp_verify_nonce( $_REQUEST[ $this->item_shortname . '_license_key-nonce' ], $this->item_shortname . '_license_key-nonce' ) ) {

				wp_die( esc_html__( 'Nonce verification failed.', 'give' ), esc_html__( 'Error', 'give' ), array( 'response' => 403 ) );

			}

			// Check if user have correct permissions.
            if ( ! current_user_can( 'manage_give_settings' ) ) {
                return;
            }

			// Allow third party addon developers to handle license activation.
			if( $this->is_third_party_addon() ){
				do_action( 'give_activate_license', $this );
				return;
			}

            // Delete previous license setting if a empty license key submitted.
            if ( empty( $_POST[ $this->item_shortname . '_license_key' ] ) ) {
                delete_option( $this->item_shortname . '_license_active' );
                return;
            }

            // Do not simultaneously activate any addon if user want to deactivate any addon.
            foreach ( $_POST as $key => $value ) {
                if ( false !== strpos( $key, 'license_key_deactivate' ) ) {
                    // Don't activate a key when deactivating a different key
                    return;
                }
            }


            // Check if plugin previously installed.
            if ( $this->is_valid_license() ) {
                return;
            }

            // Get license key.
            $license = sanitize_text_field( $_POST[ $this->item_shortname . '_license_key' ] );

			// Bailout.
			if( empty( $license ) ) {
				return;
			}

            // Data to send to the API
			$api_params = array(
				'edd_action' => 'activate_license', //never change from "edd_" to "give_"!
				'license'    => $license,
				'item_name'  => urlencode( $this->item_name ),
				'url'        => home_url()
			);

			// Call the API
			$response = wp_remote_post(
				$this->api_url,
				array(
					'timeout'   => 15,
					'sslverify' => false,
					'body'      => $api_params
				)
			);

			// Make sure there are no errors
			if ( is_wp_error( $response ) ) {
				return;
			}

			// Tell WordPress to look for updates
			set_site_transient( 'update_plugins', null );

			// Decode license data
            $license_data = json_decode( wp_remote_retrieve_body( $response ) );
            update_option( $this->item_shortname . '_license_active', $license_data );

            // Check subscription for license key and store this to db (if any).
            $this->single_subscription_check();
		}

		/**
		 * Deactivate License
		 *
		 * Deactivate the license key.
		 *
		 * @access public
		 *
		 * @return void
		 */
		public function deactivate_license() {

			if ( ! isset( $_POST[ $this->item_shortname . '_license_key' ] ) ) {
				return;
			}

			if ( ! wp_verify_nonce( $_REQUEST[ $this->item_shortname . '_license_key-nonce' ], $this->item_shortname . '_license_key-nonce' ) ) {

				wp_die( esc_html__( 'Nonce verification failed.', 'give' ), esc_html__( 'Error', 'give' ), array( 'response' => 403 ) );

			}

			if ( ! current_user_can( 'manage_give_settings' ) ) {
				return;
			}

			// Allow third party addon developers to handle license deactivation.
			if( $this->is_third_party_addon() ){
				do_action( 'give_deactivate_license', $this );
				return;
			}

			// Run on deactivate button press
			if ( isset( $_POST[ $this->item_shortname . '_license_key_deactivate' ] ) ) {

				// Data to send to the API
				$api_params = array(
					'edd_action' => 'deactivate_license', //never change from "edd_" to "give_"!
					'license'    => $this->license,
					'item_name'  => urlencode( $this->item_name ),
					'url'        => home_url()
				);

				// Call the API
				$response = wp_remote_post(
					$this->api_url,
					array(
						'timeout'   => 15,
						'sslverify' => false,
						'body'      => $api_params
					)
				);


				// Make sure there are no errors
				if ( is_wp_error( $response ) ) {
					return;
				}

				// Decode the license data
				$license_data = json_decode( wp_remote_retrieve_body( $response ) );


                // Remove license data.
				delete_option( $this->item_shortname . '_license_active' );

                // Remove license key from subscriptions if exist.
                $this->remove_license_key_from_subscriptions();
			}
		}


        /**
         * Check if license key is valid once per week
         *
         * @access  public
         * @since   1.6
         * @return  bool/void
         */
        public function weekly_license_check() {

            if( ! empty( $_POST['give_settings'] ) ) {
                // Don't fire when saving settings
                return false;
            }

            if( empty( $this->license ) ) {
                return false;
            }

	        // Allow third party addon developers to handle there license check.
	        if( $this->is_third_party_addon() ){
		        do_action( 'give_weekly_license_check', $this );
		        return false;
	        }

            // Data to send in our API request.
            $api_params = array(
                'edd_action'=> 'check_license',
                'license' 	=> $this->license,
                'item_name' => urlencode( $this->item_name ),
                'url'       => home_url()
            );

            // Call the API
            $response = wp_remote_post(
                $this->api_url,
                array(
                    'timeout'   => 15,
                    'sslverify' => false,
                    'body'      => $api_params
                )
            );

            // Make sure the response came back okay.
            if ( is_wp_error( $response ) ) {
                return false;
            }

            $license_data = json_decode( wp_remote_retrieve_body( $response ) );
            update_option( $this->item_shortname . '_license_active', $license_data );
        }


		/**
		 * Check subscription validation once per week
		 *
		 * @access  public
		 * @since   1.6
		 * @return  bool/void
		 */
		public function weekly_subscription_check() {

			if( ! empty( $_POST['give_settings'] ) ) {
				// Don't fire when saving settings
				return false;
			}

			// Remove old subscription data.
			if( absint( get_option( '_give_subscriptions_edit_last', true ) ) < current_time( 'timestamp' , 1 ) ){
				delete_option( 'give_subscriptions' );
				update_option( '_give_subscriptions_edit_last', strtotime( '+ 1 day', current_time( 'timestamp' , 1 ) ) );
			}

			if( empty( $this->license ) ) {
				return false;
			}

			// Allow third party addon developers to handle there subscription check.
			if( $this->is_third_party_addon() ){
				do_action( 'give_weekly_subscription_check', $this );
				return false;
			}

			// Delete subscription notices show blocker.
			$this->_delete_subscription_notices_show_blocker();

			// Data to send in our API request.
			$api_params = array(
				// Do not get confuse with edd_action check_subscription.
				// By default edd software licensing api does not have api to check subscription.
				// This is custom feature to check subscriptions.
				'edd_action'=> 'check_subscription',
				'license' 	=> $this->license,
				'item_name' => urlencode( $this->item_name ),
				'url'       => home_url()
			);

			// Call the API
			$response = wp_remote_post(
				$this->api_url,
				array(
					'timeout'   => 15,
					'sslverify' => false,
					'body'      => $api_params
				)
			);

			// Make sure the response came back okay.
			if ( is_wp_error( $response ) ) {
				return false;
			}

			$subscription_data = json_decode( wp_remote_retrieve_body( $response ), true );

			if( ! empty( $subscription_data['success'] ) && absint( $subscription_data['success'] ) ) {
				$subscriptions = get_option( 'give_subscriptions', array() );

				// Update subscription data only if subscription does not exist already.
				if( ! array_key_exists( $subscription_data['id'], $subscriptions ) ) {
					$subscriptions[ $subscription_data['id'] ] = $subscription_data;
					$subscriptions[ $subscription_data['id'] ]['licenses'] = array();
				}

				// Store licenses for subscription.
				if( ! in_array( $this->license, $subscriptions[ $subscription_data['id'] ]['licenses'] ) ) {
					$subscriptions[ $subscription_data['id'] ]['licenses'][] = $this->license;
				}

				update_option( 'give_subscriptions', $subscriptions );
			}
		}

        /**
         * Check if license key is part of subscription or not
         *
         * @access  public
         * @since   1.6
         * @return  bool/void
         */
        public function single_subscription_check() {

            if( ! empty( $_POST['give_settings'] ) ) {
                // Don't fire when saving settings
                return false;
            }

            if( empty( $this->license ) ) {
                return false;
            }

            // Allow third party addon developers to handle there subscription check.
            if( $this->is_third_party_addon() ){
                do_action( 'give_subscription_check', $this );
                return false;
            }

            // Delete subscription notices show blocker.
            $this->_delete_subscription_notices_show_blocker();

            // Data to send in our API request.
            $api_params = array(
                // Do not get confuse with edd_action check_subscription.
                // By default edd software licensing api does not have api to check subscription.
                // This is custom feature to check subscriptions.
                'edd_action'=> 'check_subscription',
                'license' 	=> $this->license,
                'item_name' => urlencode( $this->item_name ),
                'url'       => home_url()
            );

            // Call the API
            $response = wp_remote_post(
                $this->api_url,
                array(
                    'timeout'   => 15,
                    'sslverify' => false,
                    'body'      => $api_params
                )
            );

            // Make sure the response came back okay.
            if ( is_wp_error( $response ) ) {
                return false;
            }

            $subscription_data = json_decode( wp_remote_retrieve_body( $response ), true );

            if( ! empty( $subscription_data['success'] ) && absint( $subscription_data['success'] ) ) {
                $subscriptions = get_option( 'give_subscriptions', array() );

                // Update subscription data only if subscription does not exist already.
                if( ! array_key_exists( $subscription_data['id'], $subscriptions ) ) {
                    $subscriptions[ $subscription_data['id'] ] = $subscription_data;
                    $subscriptions[ $subscription_data['id'] ]['licenses'] = array();
                }

                // Store licenses for subscription.
                if( ! in_array( $this->license, $subscriptions[ $subscription_data['id'] ]['licenses'] ) ) {
                    $subscriptions[ $subscription_data['id'] ]['licenses'][] = $this->license;
                }

                update_option( 'give_subscriptions', $subscriptions );
            }
        }


        /**
         * Admin notices for errors
         *
         * @access  public
         * @return  void
         */
        public function notices() {
            global $current_user;
            static $showed_invalid_message;
            static $showed_subscriptions_message;
            static $addon_license_key_in_subscriptions;

	        // Set default value.
	        $addon_license_key_in_subscriptions = ! empty( $addon_license_key_in_subscriptions ) ? $addon_license_key_in_subscriptions : array();

            if( empty( $this->license ) ) {
                return;
            }

            if( ! current_user_can( 'manage_shop_settings' ) ) {
                return;
            }

            // Do not show licenses notices on license tab.
            if( ! empty( $_GET['tab'] ) && 'licenses' === $_GET['tab'] ) {
                return;
            }

            $messages = array();

	        // Get subscriptions.
	        $subscriptions = get_option( 'give_subscriptions' );


	        // Show subscription messages.
	        if( ! empty( $subscriptions ) && ! $showed_subscriptions_message ) {

	        	foreach ( $subscriptions as $subscription ) {
	        		// Subscription expires timestamp.
	        		$subscription_expires = strtotime( $subscription['expires'] );
			        
					// Start showing subscriptions message before one week of renewal date.
			        if( strtotime( '- 7 days', $subscription_expires ) > current_time( 'timestamp', 1 ) ) {
			        	continue;
			        }

	        		// Check if subscription message already exist in messages.
	        		if( array_key_exists( $subscription['id'], $messages ) ) {
	        			continue;
			        }

			        if( ( ! $this->is_notice_dismissed( $subscription['id'] ) && 'active' !== $subscription['status'] ) ) {

			            if( strtotime( $subscription['expires'] ) < current_time( 'timestamp', 1 ) ) {// Check if license already expired.
                            $messages[$subscription['id']] = sprintf(
                                __( 'You Give addon license expired for payment <a href="%s" target="_blank">#%d</a>. <a href="%s" target="_blank">Click to renew an existing license</a> or <a href="%s">Click here if already renewed</a>.', 'give' ),
                                urldecode( $subscription['invoice_url'] ),
                                $subscription['payment_id'],
                                "{$this->checkout_url}?edd_license_key={$subscription['license_key']}&utm_campaign=admin&utm_source=licenses&utm_medium=expired",
                                esc_url( add_query_arg( '_give_hide_license_notices_permanently', $subscription['id'], $_SERVER['REQUEST_URI'] ) )
                            );
                        }else{
                            $messages[$subscription['id']] = sprintf(
                                __( 'You Give addon license will expire in %s for payment <a href="%s" target="_blank">#%d</a>. <a href="%s" target="_blank">Click to renew an existing license</a> or <a href="%s">Click here if already renewed</a>.', 'give' ),
                                human_time_diff( current_time( 'timestamp', 1 ), strtotime( $subscription['expires'] ) ),
                                urldecode( $subscription['invoice_url'] ),
                                $subscription['payment_id'],
                                "{$this->checkout_url}?edd_license_key={$subscription['license_key']}&utm_campaign=admin&utm_source=licenses&utm_medium=expired",
                                esc_url( add_query_arg( '_give_hide_license_notices_permanently', $subscription['id'], $_SERVER['REQUEST_URI'] ) )
                            );
                        }
			        }

			        // Stop validation for these licencse keys.
			        $addon_license_key_in_subscriptions = array_merge( $addon_license_key_in_subscriptions, $subscription['licenses'] );
	        	}
		        $showed_subscriptions_message = true;
	        }


	        // Show non subscription addon messages.
            if( ! in_array( $this->license, $addon_license_key_in_subscriptions ) && ! $this->is_notice_dismissed( 'general' ) && ! $this->is_valid_license() && empty( $showed_invalid_message ) ) {

                $messages['general'] = sprintf(
                    __( 'You have invalid or expired license keys for Give Addon. Please go to the <a href="%s">Licenses page</a> to correct this issue.', 'give' ),
                    admin_url( 'edit.php?post_type=give_forms&page=give-settings&tab=licenses' )
                );
                $showed_invalid_message = true;

            }

			// Print messages.
            if( ! empty( $messages ) ) {
                foreach( $messages as $notice_id => $message ) {
                    echo '<div class="notice notice-error is-dismissible give-license-notice" data-dismiss-notice-shortly="' . esc_url( add_query_arg( '_give_hide_license_notices_shortly', $notice_id, $_SERVER['REQUEST_URI'] ) ) . '">';
                    echo '<p>' . $message . '</p>';
                    echo '</div>';
                }
            }
        }


        /**
         * Check if license is valid or not.
         * @return bool
         */
		public function is_valid_license() {
            if( apply_filters( 'give_is_valid_license' , ( is_object( $this->license_data ) && ! empty( $this->license_data ) && 'valid' === $this->license_data->license ) ) ) {
                return true;
            }

            return false;
        }

		/**
		 * Check if license is valid or not.
		 * @return bool
		 */
		public function is_third_party_addon() {
			return ( false === strpos( $this->api_url, 'give-playground.dev/' ) );
		}


        /**
         * Remove license keyy from subscription
         *
         * This function mainly uses when admin user deactivate license key,
         * then we do not need subscription information for that license key.
         *
         * @since  1.6
         * @access public
         *
         * @return void
         */
		public function remove_license_key_from_subscriptions(){
            $subscriptions = get_option( 'give_subscriptions', array() );

            if( ! empty( $subscriptions ) ) {
                foreach ( $subscriptions as $subscription_id => $subscription ) {
                    $license_index = array_search( $this->license, $subscription['licenses'] );
                    if( false !== $license_index ) {
                        // Remove license key.
                        unset( $subscriptions[ $subscription_id ]['licenses'][$license_index] );

                        // Rearrange license keys.
                        $subscriptions[ $subscription_id ]['licenses'] = array_values( $subscriptions[ $subscription_id ]['licenses'] );

                        // Update subscription information.
                        update_option( 'give_subscriptions', $subscriptions );
                        break;
                    }
                }
            }
        }

		/**
         * Delete subscription notices show blocker.
         *
         * @since 1.6
         * @access private
         *
         * @return void
         */
		private function _delete_subscription_notices_show_blocker(){
            delete_option( '_give_hide_license_notices_permanently' );
        }

        /**
         * Check if notice dismissed by admin user or not.
         *
         * @param int $notice_id notice ID.
         *
         * @return bool
         */
        public function is_notice_dismissed( $notice_id ){
            global $current_user;
            $is_notice_dismissed = false;

            // Ge is notice dismissed permanently.
            $already_dismiss_notices = ( $already_dismiss_notices = get_user_meta( $current_user->ID, '_give_hide_license_notices_permanently', true ) )
                ? $already_dismiss_notices
                : array();


            if( in_array( $notice_id, $already_dismiss_notices ) || get_transient( "_give_hide_license_notices_shortly_{$current_user->ID}_{$notice_id}" ) ) {
                $is_notice_dismissed =  true;
            }

            return apply_filters( 'give_is_license_notice_dismissed', $is_notice_dismissed, $notice_id, $current_user );
        }
	}

endif; // end class_exists check
