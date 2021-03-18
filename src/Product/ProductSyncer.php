<?php
declare( strict_types=1 );

namespace Automattic\WooCommerce\GoogleListingsAndAds\Product;

use Automattic\WooCommerce\GoogleListingsAndAds\Google\BatchProductRequestEntry;
use Automattic\WooCommerce\GoogleListingsAndAds\Google\BatchProductResponse;
use Automattic\WooCommerce\GoogleListingsAndAds\Google\GoogleProductService;
use Automattic\WooCommerce\GoogleListingsAndAds\HelperTraits\MerchantCenterTrait;
use Automattic\WooCommerce\GoogleListingsAndAds\Infrastructure\Service;
use Automattic\WooCommerce\GoogleListingsAndAds\Options\OptionsAwareInterface;
use Exception;
use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Class ProductSyncer
 *
 * @package Automattic\WooCommerce\GoogleListingsAndAds\Product
 */
class ProductSyncer implements Service, OptionsAwareInterface {

	public const FAILURE_THRESHOLD        = 5;         // Number of failed attempts allowed per FAILURE_THRESHOLD_WINDOW
	public const FAILURE_THRESHOLD_WINDOW = '3 hours'; // PHP supported Date and Time format: https://www.php.net/manual/en/datetime.formats.php

	use MerchantCenterTrait;

	/**
	 * @var GoogleProductService
	 */
	protected $google_service;

	/**
	 * @var BatchProductHelper
	 */
	protected $batch_helper;

	/**
	 * ProductSyncer constructor.
	 *
	 * @param GoogleProductService $google_service
	 * @param BatchProductHelper   $batch_helper
	 */
	public function __construct( GoogleProductService $google_service, BatchProductHelper $batch_helper ) {
		$this->google_service = $google_service;
		$this->batch_helper   = $batch_helper;
	}

	/**
	 * Submits an array of WooCommerce products to Google Merchant Center.
	 *
	 * @param WC_Product[] $products
	 *
	 * @return BatchProductResponse Containing both the synced and invalid products.
	 *
	 * @throws ProductSyncerException If there are any errors while syncing products with Google Merchant Center.
	 */
	public function update( array $products ): BatchProductResponse {
		$this->validate_merchant_center_setup();

		// prepare and validate products
		$product_entries = $this->batch_helper->validate_and_generate_update_request_entries( $products );

		return $this->update_by_batch_requests( $product_entries );
	}

	/**
	 * Submits an array of WooCommerce products to Google Merchant Center.
	 *
	 * @param BatchProductRequestEntry[] $product_entries
	 *
	 * @return BatchProductResponse Containing both the synced and invalid products.
	 *
	 * @throws ProductSyncerException If there are any errors while syncing products with Google Merchant Center.
	 */
	public function update_by_batch_requests( array $product_entries ): BatchProductResponse {
		$this->validate_merchant_center_setup();

		// bail if no valid products provided
		if ( empty( $product_entries ) ) {
			return new BatchProductResponse( [], [] );
		}

		$updated_products = [];
		$invalid_products = [];
		foreach ( array_chunk( $product_entries, GoogleProductService::BATCH_SIZE ) as $product_entries ) {
			try {
				$response = $this->google_service->insert_batch( $product_entries );

				$updated_products = array_merge( $updated_products, $response->get_products() );
				$invalid_products = array_merge( $invalid_products, $response->get_errors() );

				// update the meta data for the synced and invalid products
				array_walk( $updated_products, [ $this->batch_helper, 'mark_as_synced' ] );
				array_walk( $invalid_products, [ $this->batch_helper, 'mark_as_invalid' ] );
			} catch ( Exception $exception ) {
				throw new ProductSyncerException( sprintf( 'Error updating Google product: %s', $exception->getMessage() ), 0, $exception );
			}
		}

		$internal_error_products = $this->batch_helper->get_internal_error_products( $invalid_products );
		if ( ! empty( $internal_error_products ) && apply_filters( 'gla_products_update_retry_on_failure', true, $invalid_products ) ) {
			do_action( 'gla_batch_retry_update_products', $internal_error_products );
		}

		do_action(
			'gla_batch_updated_products',
			$updated_products,
			$invalid_products
		);

		return new BatchProductResponse( $updated_products, $invalid_products );
	}

	/**
	 * Deletes an array of WooCommerce products from Google Merchant Center.
	 *
	 * @param WC_Product[] $products
	 *
	 * @return BatchProductResponse Containing both the deleted and invalid products.
	 *
	 * @throws ProductSyncerException If there are any errors while deleting products from Google Merchant Center.
	 */
	public function delete( array $products ): BatchProductResponse {
		$this->validate_merchant_center_setup();

		// filter the synced products
		$synced_products = $this->batch_helper->filter_synced_products( $products );
		$product_entries = $this->batch_helper->generate_delete_request_entries( $synced_products );

		return $this->delete_by_batch_requests( $product_entries );
	}

	/**
	 * Deletes an array of WooCommerce products from Google Merchant Center.
	 *
	 * Note: This method does not automatically delete variations of a parent product. They each must be provided via the $product_entries argument.
	 *
	 * @param BatchProductRequestEntry[] $product_entries
	 *
	 * @return BatchProductResponse Containing both the deleted and invalid products (including their variation).
	 *
	 * @throws ProductSyncerException If there are any errors while deleting products from Google Merchant Center.
	 */
	public function delete_by_batch_requests( array $product_entries ): BatchProductResponse {
		$this->validate_merchant_center_setup();

		// return empty response if no synced product found
		if ( empty( $product_entries ) ) {
			return new BatchProductResponse( [], [] );
		}

		$deleted_products = [];
		$invalid_products = [];
		foreach ( array_chunk( $product_entries, GoogleProductService::BATCH_SIZE ) as $product_entries ) {
			try {
				$response = $this->google_service->delete_batch( $product_entries );

				$deleted_products = array_merge( $deleted_products, $response->get_products() );
				$invalid_products = array_merge( $invalid_products, $response->get_errors() );

				array_walk( $deleted_products, [ $this->batch_helper, 'mark_as_unsynced' ] );
			} catch ( Exception $exception ) {
				throw new ProductSyncerException( sprintf( 'Error deleting Google products: %s', $exception->getMessage() ), 0, $exception );
			}
		}

		$internal_error_products = $this->batch_helper->get_internal_error_products( $invalid_products );
		if ( ! empty( $internal_error_products ) && apply_filters( 'gla_products_delete_retry_on_failure', true, $invalid_products ) ) {
			$id_map     = BatchProductRequestEntry::convert_to_id_map( $product_entries );
			$failed_ids = array_intersect( $id_map->get(), $internal_error_products );

			do_action( 'gla_batch_retry_delete_products', $failed_ids );
		}

		do_action(
			'gla_batch_deleted_products',
			$deleted_products,
			$invalid_products
		);

		return new BatchProductResponse( $deleted_products, $invalid_products );
	}

	/**
	 * Validates whether Merchant Center is set up and connected.
	 *
	 * @throws ProductSyncerException If Google Merchant Center is not set up and connected.
	 */
	protected function validate_merchant_center_setup(): void {
		if ( ! $this->setup_complete() ) {
			throw new ProductSyncerException( __( 'Google Merchant Center has not been set up correctly. Please review your configuration.', 'google-listings-and-ads' ) );
		}
	}
}
