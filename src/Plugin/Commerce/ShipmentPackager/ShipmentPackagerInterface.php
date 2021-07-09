<?php

namespace Drupal\commerce_packaging\Plugin\Commerce\ShipmentPackager;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodInterface;

interface ShipmentPackagerInterface {

  /**
   * Create packages for the provided shipment.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   * @param \Drupal\commerce_shipping\ShipmentItem[]
   *   The unpackaged shipment items.
   */
  public function packageItems(ShipmentInterface $shipment, array $unpackaged_items);

}
