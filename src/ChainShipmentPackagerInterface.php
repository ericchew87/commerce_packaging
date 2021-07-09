<?php

namespace Drupal\commerce_packaging;


use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Entity\ShippingMethodInterface;

interface ChainShipmentPackagerInterface {

  /**
   * Gets the packaging strategy.
   *
   * @param \Drupal\commerce_shipping\Entity\ShippingMethodInterface $shipping_method
   *   The shipping method.
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface
   *   The shipment.
   *
   * @return \Drupal\commerce_packaging\Entity\PackagingStrategyInterface
   *   The packaging strategy
   */
  public function getPackagingStrategy(ShippingMethodInterface $shipping_method, ShipmentInterface $shipment);

  /**
   * Packages the shipment with proposed shipment packages..
   *
   * @param \Drupal\commerce_shipping\Entity\ShippingMethodInterface $shipping_method
   *   The shipping method.
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   *
   * @return \Drupal\commerce_shipping\Entity\ShipmentInterface
   *   The shipment with proposed shipment packages.
   */
  public function packageShipment(ShippingMethodInterface $shipping_method, ShipmentInterface $shipment);

  /**
   * Finalizes the packages on the shipment.
   *
   * Converts proposed shipment packages to shipment package entities
   * and adds references to and from the shipment.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   *
   * @return \Drupal\commerce_shipping\Entity\ShipmentInterface
   *   The shipment with packaged items.
   */
  public function finalizePackages(ShipmentInterface $shipment);

}
