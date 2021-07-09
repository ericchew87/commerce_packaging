<?php

namespace Drupal\commerce_packaging\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining Packaging strategy entities.
 */
interface PackagingStrategyInterface extends ConfigEntityInterface {

  /**
   * Gets the default package type.
   *
   * @return \Drupal\commerce_shipping\Plugin\Commerce\PackageType\PackageTypeInterface
   *   The default package type.
   */
  public function getDefaultPackageType();

  /**
   * Gets the shipment packagers.
   *
   * @return \Drupal\commerce_packaging\Plugin\Commerce\ShipmentPackager\ShipmentPackagerInterface[]
   *   The shipment packagers.
   */
  public function getShipmentPackagers();
}
