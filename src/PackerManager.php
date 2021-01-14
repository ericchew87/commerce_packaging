<?php


namespace Drupal\commerce_packaging;


use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_shipping\PackerManager as PackerManagerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\profile\Entity\ProfileInterface;

class PackerManager extends PackerManagerBase {

  /**
   * The shipment packager.
   *
   * @var \Drupal\commerce_packaging\ChainShipmentPackagerInterface
   */
  protected $shipmentPackager;

  /**
   * Constructs a new PackerManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_packaging\ChainShipmentPackagerInterface $shipment_packager
   *   The shipment packager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ChainShipmentPackagerInterface $shipment_packager) {
    parent::__construct($entity_type_manager);
    $this->entityTypeManager = $entity_type_manager;
    $this->shipmentPackager = $shipment_packager;
  }

  /**
   * {@inheritDoc}
   */
  public function packToShipments(OrderInterface $order, ProfileInterface $shipping_profile, array $shipments) {
    $shipments_array = parent::packToShipments($order, $shipping_profile, $shipments);
    $populated_shipments = reset($shipments_array);
    foreach ($populated_shipments as $populated_shipment) {
      $this->shipmentPackager->packageShipment($populated_shipment);
    }

    return $shipments_array;
  }

}
