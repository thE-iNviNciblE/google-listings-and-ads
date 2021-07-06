<?php
declare( strict_types=1 );

namespace Automattic\WooCommerce\GoogleListingsAndAds\Internal\Requirements;

use Automattic\WooCommerce\GoogleListingsAndAds\Exception\ExtensionRequirementException;
use Automattic\WooCommerce\GoogleListingsAndAds\MerchantCenter\MerchantStatuses;
use Automattic\WooCommerce\GoogleListingsAndAds\Options\TransientsInterface;
use Automattic\WooCommerce\GoogleListingsAndAds\PluginHelper;
use DateTime;

defined( 'ABSPATH' ) || exit;

/**
 * Class GoogleProductFeedValidator
 *
 * @since x.x.x
 *
 * @package AutomatticWooCommerceGoogleListingsAndAdsInternalRequirements
 */
class GoogleProductFeedValidator extends RequirementValidator {

	use PluginHelper;

	/**
	 * Validate all requirements for the plugin to function properly.
	 *
	 * @return bool
	 */
	public function validate(): bool {
		try {
			$this->validate_google_product_feed_inactive();
		} catch ( ExtensionRequirementException $e ) {

			add_filter(
				'woocommerce_gla_account_issues',
				function( $issues, $current_time ) {
					return $this->add_conflict_issue( $issues, $current_time );
				},
				10,
				2
			);

			add_filter(
				'deactivated_plugin',
				function( $plugin ) {
					if ( 'woocommerce-product-feeds/woocommerce-gpf.php' === $plugin ) {
						woogle_get_container()->get( MerchantStatuses::class )->clear_cache();
					}
				}
			);
		}
		return true;
	}

	/**
	 * Validate that Google Product Feed isn't enabled.
	 *
	 * @throws ExtensionRequirementException When Google Product Feed is active.
	 */
	protected function validate_google_product_feed_inactive() {
		if ( defined( 'WOOCOMMERCE_GPF_VERSION' ) ) {
			throw ExtensionRequirementException::incompatible_plugin( 'Google Product Feed' );
		}
	}

	/**
	 * Add an account-level issue regarding the plugin conflict
	 * to the array of issues to be saved in the database.
	 *
	 * @param array    $issues The current array of account-level issues
	 * @param DateTime $current_time The time of the cache/issues generation.
	 *
	 * @return array The issues with the new conflict issue included
	 */
	protected function add_conflict_issue( array $issues, DateTime $current_time ): array {
		foreach ( $issues as &$issue ) {
			// Make sure all issues have the source attribute to avoid errors.
			if ( ! empty( $issue['source'] ) ) {
				continue;
			}
			$issue['source'] = 'mc';
		}

		$issues[] = [
			'product_id' => '0',
			'product'    => 'All products',
			'code'       => 'incompatible_google_product_feed',
			'issue'      => 'The Google Product Feed plugin may cause conflicts or unexpected results.',
			'action'     => 'Delete or deactivate the Google Product Feed plugin from your store',
			'action_url' => 'https://developers.google.com/shopping-content/guides/best-practices#do-not-use-api-and-feeds',
			'created_at' => $current_time->format( 'Y-m-d H:i:s' ),
			'type'       => MerchantStatuses::TYPE_ACCOUNT,
			'severity'   => 'error',
			'source'     => 'filter',
		];

		return $issues;
	}
}
