<?php

namespace Drupal\commerce_packaging;


use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodInterface;

interface ChainShipmentPackagerInterface {

  /**
   * Gets the shipment packager plugins.
   *
   * @param \Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodInterface|null $shipping_method_plugin
   *   The shipping method plugin or NULL to use global packaging.
   *
   * @return \Drupal\commerce_packaging\Plugin\Commerce\ShipmentPackager\ShipmentPackagerInterface[]
   *   The shipment packager plugins.
   */
  public function getEnabledPackagers(ShippingMethodInterface $shipping_method_plugin = NULL);

  /**
   * Clones a shipment and returns it with packaged items.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   * @param \Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodInterface|null $shipping_method_plugin
   *   The shipping method plugin or NULL to use global packaging.
   *
   * @return \Drupal\commerce_shipping\Entity\ShipmentInterface
   *   The cloned shipment with packaged items.
   */
  public function packageShipment(ShipmentInterface $shipment, ShippingMethodInterface $shipping_method_plugin = NULL);

  /**
   * Gets whether the shipping method has custom packaging settings.
   *
   * @param \Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodInterface $shipping_method_plugin
   *   The shipping method plugin.
   *
   * @return bool
   *   TRUE if the shipping method has custom packaging settings, FALSE otherwise.
   */
  public function hasCustomPackaging(ShippingMethodInterface $shipping_method_plugin);

}
