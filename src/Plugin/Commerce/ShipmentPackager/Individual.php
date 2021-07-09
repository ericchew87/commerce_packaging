<?php

namespace Drupal\commerce_packaging\Plugin\Commerce\ShipmentPackager;

use Drupal\commerce_packaging\ProposedShipmentPackage;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodInterface;

/**
 * Provides the default_package_individual shipment packager.
 *
 * @CommerceShipmentPackager(
 *   id = "individual",
 *   label = @Translation("Individual"),
 *   description = @Translation("Places each shipment item into its own default package."),
 * )
 */
class Individual extends ShipmentPackagerBase {

  /**
   * {@inheritdoc}
   */
  public function packageItems(ShipmentInterface $shipment, array $unpackaged_items) {
    $proposed_shipment_packages = [];
    foreach ($unpackaged_items as $unpackaged_item) {
      for ($i = 0; $i < $unpackaged_item->getQuantity(); $i++) {
        $split_unpacked_item = $this->splitShipmentItem($unpackaged_item, 1);
        $proposed_shipment_package = new ProposedShipmentPackage([
          'type' => $this->getShipmentPackageType($shipment),
          'shipment_id' => $shipment->id(),
          'items' => [$split_unpacked_item],
          'title' => $shipment->getPackageType()->getLabel() . '-' . $i,
          'package_type' => $shipment->getPackageType(),
          'weight' => $split_unpacked_item->getWeight(),
          'declared_value' => $split_unpacked_item->getDeclaredValue(),
        ]);

        $proposed_shipment_packages[] = $proposed_shipment_package;
      }
    }

    return [$proposed_shipment_packages, []];
  }



}
