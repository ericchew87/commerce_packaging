<?php

namespace Drupal\commerce_packaging\Resolver;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Entity\ShippingMethodInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides the default packaging strategy, taking it directly from the shipping method.
 */
class DefaultPackagingStrategyResolver implements PackagingStrategyResolverInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * DefaultPackagingStrategyResolver constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(ShippingMethodInterface $shipping_method, ShipmentInterface $shipment) {
    $configuration = $shipping_method->getPlugin()->getConfiguration();
    if (!empty($configuration['commerce_packaging_options']['packaging_strategy'])) {
      $packaging_strategy_id = $configuration['commerce_packaging_options']['packaging_strategy'];
      $packaging_strategy_storage = $this->entityTypeManager->getStorage('commerce_packaging_strategy');
      /** @var \Drupal\commerce_packaging\Entity\PackagingStrategyInterface $packaging_strategy */
      if ($packaging_strategy = $packaging_strategy_storage->load($packaging_strategy_id)) {
        return $packaging_strategy;
      }
    }

    return null;
  }

}
