<?php

namespace Drupal\commerce_packaging;


use Drupal\commerce_shipping\Entity\ShipmentInterface;

interface ChainShipmentPackagerInterface {

  /**
   * Gets the shipment packager plugins.
   *
   * @return \Drupal\commerce_packaging\Plugin\Commerce\ShipmentPackager\ShipmentPackagerInterface[]
   *   The shipment packager plugins.
   */
  public function getEnabledPackagers();

  /**
   * Packages the shipment.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   *
   * @return \Drupal\commerce_shipping\Entity\ShipmentInterface
   *   The shipment with packaged items.
   */
  public function packageShipment(ShipmentInterface $shipment);

}
