<?php

namespace Drupal\commerce_packaging\Plugin\Commerce\ShipmentPackager;

use Drupal\commerce_packaging\ProposedShipmentPackage;
use Drupal\commerce_shipping\Entity\ShipmentInterface;

/**
 * Provides the all_in_one shipment packager.
 *
 * @CommerceShipmentPackager(
 *   id = "all_in_one",
 *   label = @Translation("All In One"),
 *   description = @Translation("Places all shipment items into the default package."),
 * )
 */
class AllInOne extends ShipmentPackagerBase {

  /**
   * {@inheritdoc}
   */
  public function packageItems(ShipmentInterface $shipment, array $unpackaged_items) {
    $weight = NULL;
    $declared_value = NULL;
    /** @var \Drupal\commerce_shipping\ShipmentItem $unpackaged_item */
    foreach ($unpackaged_items as $unpackaged_item) {
      $weight = is_null($weight) ? $unpackaged_item->getWeight() : $weight->add($unpackaged_item->getWeight());
      $declared_value = is_null($declared_value) ? $unpackaged_item->getDeclaredValue() : $declared_value->add($unpackaged_item->getDeclaredValue());
    }

    $proposed_shipment_package = new ProposedShipmentPackage([
      'type' => $this->getShipmentPackageType($shipment),
      'shipment_id' => $shipment->id(),
      'items' => $unpackaged_items,
      'title' => $shipment->getPackageType()->getLabel(),
      'package_type' => $shipment->getPackageType(),
      'weight' => $weight,
      'declared_value' => $declared_value,
    ]);

    return [[$proposed_shipment_package], []];
  }

}
