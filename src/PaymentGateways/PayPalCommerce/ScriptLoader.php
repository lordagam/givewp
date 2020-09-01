<?php

namespace Give\PaymentGateways\PayPalCommerce;

use Give\PaymentGateways\PayPalCommerce\Models\MerchantDetail;
use Give\PaymentGateways\PayPalCommerce\Repositories\MerchantDetails;
use Give_Admin_Settings;
use PayPalCheckoutSdk\Core\AccessTokenRequest;

/**
 * Class ScriptLoader
 * @since 2.9.0
 * @package Give\PaymentGateways\PayPalCommerce
 *
 */
class ScriptLoader {
	/**
	 * Paypal SDK handle.
	 *
	 * @since 2.9.0
	 *
	 * @var string
	 */
	private $paypalSdkScriptHandle = 'give-paypal-sdk-js';

	/**
	 * @since 2.9.0
	 *
	 * @var MerchantDetails
	 */
	private $merchantRepository;

	/**
	 * ScriptLoader constructor.
	 *
	 * @since 2.9.0
	 *
	 * @param MerchantDetails $merchantRepository
	 */
	public function __construct( MerchantDetails $merchantRepository ) {
		$this->merchantRepository = $merchantRepository;
	}

	/**
	 * Load admin scripts
	 *
	 * @since 2.9.0
	 */
	public function loadAdminScripts() {
		if ( ! Give_Admin_Settings::is_setting_page( 'gateway', 'paypal' ) ) {
			return;
		}

		wp_enqueue_script(
			'give-paypal-partner-js',
			$this->getPartnerJsUrl(),
			[],
			null,
			true
		);

		wp_enqueue_style(
			'give-admin-paypal-commerce-css',
			GIVE_PLUGIN_URL . 'assets/dist/css/admin-paypal-commerce.css',
			[],
			GIVE_VERSION
		);

		wp_localize_script(
			'give-paypal-partner-js',
			'givePayPalCommerce',
			[
				'translations' => [
					'confirmPaypalAccountDisconnection' => esc_html__( 'Confirm PayPal account disconnection', 'give' ),
					'disconnectPayPalAccount'           => esc_html__( 'Do you want to disconnect PayPal account?', 'give' ),
					'connectSuccessTitle'               => esc_html__( 'You’re connected to PayPal! Here’s what’s next...', 'give' ),
					'pciWarning'                        => sprintf(
						__(
							'PayPal allows you to accept credit or debit cards directly on your website. Because of
							this, your site needs to maintain <a href="%1$s" target="_blank">PCI-DDS compliance</a>.
							GiveWP never stores sensitive information like card details to your server and works
							seamlessly with SSL certificates. Compliance is comprised of, but not limited to:',
							'give'
						),
						'https://givewp.com/documentation/resources/pci-compliance/'
					),
					'pciComplianceInstructions'         => [
						esc_html__( 'Using a trusted, secure hosting provider – preferably one which claims and actively promotes PCI compliance.', 'give' ),
						esc_html__( 'Maintain security best practices when setting passwords and limit access to your server.', 'give' ),
						esc_html__( 'Implement an SSL certificate to keep your donations secure.', 'give' ),
						esc_html__( 'Keep installed plugins to a minimum.', 'give' ),
						esc_html__( 'Keep plugins up to date to ensure latest security fixes are present.', 'give' ),
					],
					'liveWarning'                       => give_is_test_mode() ? esc_html__(
						'You have connected your account for test mode. You will need to connect again once you
						are in live mode.',
						'give'
					) : '',
				],
			]
		);

		$script = <<<EOT
				function givePayPalOnBoardedCallback(authCode, sharedId) {
					const query = '&authCode=' + authCode + '&sharedId=' + sharedId;
					fetch( ajaxurl + '?action=give_paypal_commerce_user_on_boarded' + query )
						.then(function(res){ return res.json() })
						.then(function(res) {
							if ( true !== res.success ) {
								alert("Something went wrong!");
								}
							}
						);
				}
EOT;

		wp_add_inline_script(
			'give-paypal-partner-js',
			$script
		);
	}

	/**
	 * Load public assets.
	 *
	 * @since 2.9.0
	 */
	public function loadPublicAssets() {
		if ( ! $this->merchantRepository->getDetails() || ! Utils::gatewayIsActive() ) {
			return;
		}

		/* @var MerchantDetail $merchant */
		$merchant = give( MerchantDetail::class );

		/**
		 * List of PayPal query parameters: https://developer.paypal.com/docs/checkout/reference/customize-sdk/#query-parameters
		 */
		$payPalSdkQueryParameters = [
			'client-id'       => $merchant->clientId,
			'merchant-id'     => $merchant->merchantIdInPayPal,
			'currency'        => give_get_currency(),
			'components'      => 'hosted-fields,buttons',
			'locale'          => get_locale(),
			'disable-funding' => 'credit',
		];

		wp_enqueue_script(
			$this->paypalSdkScriptHandle,
			add_query_arg( $payPalSdkQueryParameters, 'https://www.paypal.com/sdk/js' ),
			[ 'give' ],
			null,
			false
		);

		add_filter( 'script_loader_tag', [ $this, 'addAttributesToPayPalSdkScript' ], 10, 2 );

		wp_enqueue_script(
			'give-paypal-commerce-js',
			GIVE_PLUGIN_URL . 'assets/dist/js/paypal-commerce.js',
			[ $this->paypalSdkScriptHandle ],
			GIVE_VERSION,
			true
		);

		wp_enqueue_style(
			'give-paypal-commerce-css',
			GIVE_PLUGIN_URL . 'assets/dist/css/paypal-commerce.css',
			[ 'give-styles' ],
			GIVE_VERSION
		);

		wp_localize_script(
			'give-paypal-commerce-js',
			'givePayPalCommerce',
			[
				'paypalCardInfoErrorPrefixes'           => [
					'expirationDateField' => esc_html__( 'Card Expiration Date:', 'give' ),
					'cardNumberField'     => esc_html__( 'Card Number:', 'give' ),
					'cardCvcField'        => esc_html__( 'Card CVC:', 'give' ),
				],
				'cardFieldPlaceholders'                 => [
					'cardNumber'     => esc_html__( 'Card Number', 'give' ),
					'cardCvc'        => esc_html__( 'CVC', 'give' ),
					'expirationDate' => esc_html__( 'MM/YY', 'give' ),
				],
				'defaultDonationCreationError'          => esc_html__( 'An error occurred while processing your payment. Please try again.', 'give' ),
				'failedPaymentProcessingNotice'         => esc_html__( 'There was a problem processing your credit card. Please try again. If the problem persists, please try another payment method.', 'give' ),
				'threeDsCardAuthenticationFailedNotice' => esc_html__( 'There was a problem authenticating your payment method. Please try again. If the problem persists, please try another payment method.', 'give' ),
				'errorCodeLabel'                        => esc_html__( 'Error Code', 'give' ),
				// List of style properties support by PayPal for advanced card fields: https://developer.paypal.com/docs/business/checkout/reference/style-guide/#style-the-card-payments-fields
				'hostedCardFieldStyles'                 => apply_filters( 'give_paypal_commerce_hosted_field_style', [] ),
				'supportsCustomPayments'                => $merchant->supportsCustomPayments ? 1 : '',
				'accountCountry'                        => $merchant->accountCountry,
				'separatorLabel'                        => esc_html__( 'Or pay with card', 'give' ),
			]
		);
	}

	/**
	 * Add attributes to PayPal sdk.
	 *
	 * @since 2.9.0
	 *
	 * @param string $handle
	 *
	 * @param string $tag
	 *
	 * @return string
	 */
	public function addAttributesToPayPalSdkScript( $tag, $handle ) {
		if ( $this->paypalSdkScriptHandle !== $handle ) {
			return $tag;
		}

		$tag = str_replace(
			'src=',
			sprintf(
				'data-partner-attribution-id="%1$s" data-client-token="%2$s" src=',
				give( 'PAYPAL_COMMERCE_ATTRIBUTION_ID' ),
				$this->merchantRepository->getClientToken()
			),
			$tag
		);

		return $tag;
	}

	/**
	 * Get PayPal partner js url.
	 *
	 * @since 2.9.0
	 *
	 * @return string
	 */
	private function getPartnerJsUrl() {
		return sprintf(
			'%1$swebapps/merchantboarding/js/lib/lightbox/partner.js',
			give( PayPalClient::class )->getHomePageUrl()
		);
	}
}
