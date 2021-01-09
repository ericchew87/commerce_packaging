<?php

namespace Drupal\commerce_packaging\Plugin\Commerce\ShipmentPackager;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodInterface;

/**
 * Provides the all_in_one shipment packager.
 *
 * @CommerceShipmentPackager(
 *   id = "all_in_one",
 *   label = @Translation("All In One"),
 *   description = @Translation("Places all shipment items into the default package specified by the shipping method."),
 * )
 */
class AllInOne extends ShipmentPackagerBase {

  /**
   * {@inheritdoc}
   */
  public function packageItems(ShipmentInterface $shipment, ShippingMethodInterface $shipping_method) {
    $shipment->setPackageType($shipping_method->getDefaultPackageType());
    /** @var \Drupal\commerce_shipping\ShipmentItem[] $items */
    $items = $shipment->getData('unpackaged_items', [$shipment->getItems()]);
    /** @var \Drupal\commerce_packaging\Entity\ShipmentPackageInterface $package */
    $package = $this->entityTypeManager->getStorage('commerce_shipment_package')->create([
      'type' => $this->getShipmentPackageType($shipment),
      'items' => $items,
      'title' => $shipment->getPackageType()->getLabel(),
      'package_type' => $shipping_method->getDefaultPackageType()->getId(),
      'declared_value' => $shipment->getTotalDeclaredValue(),
      'weight' => $shipment->getWeight(),
    ]);
    $shipment->get('packages')->appendItem($package);
    $this->updatePackagedItems($shipment, $items);
    $shipment->setData('unpackaged_items', []);
  }

}
