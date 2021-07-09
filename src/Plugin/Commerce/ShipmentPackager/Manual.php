<?php

namespace Drupal\commerce_packaging\Plugin\Commerce\ShipmentPackager;

use Drupal\commerce_packaging\ProposedShipmentPackage;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodInterface;

/**
 * Provides the manual_packager shipment packager.
 *
 * @CommerceShipmentPackager(
 *   id = "manual",
 *   label = @Translation("Manual"),
 *   description = @Translation("Uses the packaging field from product variations to place items into the specified package."),
 * )
 */
class Manual extends ShipmentPackagerBase {

  /**
   * {@inheritdoc}
   */
  public function packageItems(ShipmentInterface $shipment, array $unpackaged_items) {
    $proposed_shipment_packages = [];

    foreach ($unpackaged_items as $key => $unpackaged_item) {
      /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
      $order_item = $this->entityTypeManager->getStorage('commerce_order_item')->load($unpackaged_item->getOrderItemId());
      $purchased_entity = $order_item->getPurchasedEntity();
      if ($purchased_entity->hasField('packaging') && $purchased_entity->hasField('weight')) {
        $packaging_options = $purchased_entity->get('packaging')->getValue();
        $item_qty = $unpackaged_item->getQuantity();
        // The packaging option with the highest maximum is always used first.
        foreach ($packaging_options as $packaging_option) {
          while ($item_qty >= $packaging_option['max']) {
            $split_unpackaged_item = $this->splitShipmentItem($unpackaged_item, $packaging_option['max']);
            $proposed_shipment_package = new ProposedShipmentPackage([
              'type' => $this->getShipmentPackageType($shipment),
              'shipment_id' => $shipment->id(),
              'items' => [$split_unpackaged_item],
              'title' => $shipment->getPackageType()->getLabel(),
              'package_type' => $shipment->getPackageType(),
              'weight' => $split_unpackaged_item->getWeight(),
              'declared_value' => $split_unpackaged_item->getDeclaredValue(),
            ]);
            $proposed_shipment_packages[] = $proposed_shipment_package;
            $item_qty -= $packaging_option['max'];
            if ($item_qty == 0) {
              unset($unpackaged_items[$key]);
            }
          }

          if ($item_qty > 0 && $item_qty >= $packaging_option['min'] && $item_qty <= $packaging_option['max']) {
            $split_unpackaged_item = $this->splitShipmentItem($unpackaged_item, $item_qty);
            $proposed_shipment_package = new ProposedShipmentPackage([
              'type' => $this->getShipmentPackageType($shipment),
              'shipment_id' => $shipment->id(),
              'items' => [$split_unpackaged_item],
              'title' => $shipment->getPackageType()->getLabel(),
              'package_type' => $shipment->getPackageType(),
              'weight' => $split_unpackaged_item->getWeight(),
              'declared_value' => $split_unpackaged_item->getDeclaredValue(),
            ]);
            $proposed_shipment_packages[] = $proposed_shipment_package;
            unset($unpackaged_items[$key]);
            break;
          }

          elseif ($item_qty > 0) {
            $unpackaged_items[$key] = $this->splitShipmentItem($unpackaged_item, $item_qty);
          }
        }
      }
    }

    return [$proposed_shipment_packages, $unpackaged_items];
  }

}
