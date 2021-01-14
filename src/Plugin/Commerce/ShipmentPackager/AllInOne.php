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
  public function packageItems(ShipmentInterface $shipment) {
    /** @var \Drupal\commerce_shipping\ShipmentItem[] $unpackaged_items */
    $unpackaged_items = $shipment->getData('unpackaged_items');
    /** @var \Drupal\commerce_packaging\Entity\ShipmentPackageInterface $package */
    $package = $this->entityTypeManager->getStorage('commerce_shipment_package')->create([
      'type' => $this->getShipmentPackageType($shipment),
      'items' => $unpackaged_items,
      'title' => $shipment->getPackageType()->getLabel(),
      'package_type' => $shipment->getPackageType()->getId(),
      'declared_value' => $shipment->getTotalDeclaredValue(),
      'weight' => $shipment->getWeight(),
    ]);
    $shipment->get('packages')->appendItem($package);
    $this->updatePackagedItems($shipment, $unpackaged_items);
    $shipment->setData('unpackaged_items', []);
  }

}
