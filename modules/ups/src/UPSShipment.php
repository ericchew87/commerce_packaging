<?php

namespace Drupal\commerce_packaging_ups;

use Drupal\commerce_packaging\ChainShipmentPackagerInterface;
use Drupal\commerce_packaging\ShippingMethodPackagingTrait;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodInterface;
use Drupal\commerce_ups\UPSShipment as UPSShipmentBase;
use Ups\Entity\Package as UPSPackage;
use Ups\Entity\PackageWeight;
use Ups\Entity\Shipment as APIShipment;

class UPSShipment extends UPSShipmentBase {

  use ShippingMethodPackagingTrait;

  /**
   * The current package being processed.
   *
   * @var \Drupal\commerce_packaging\Entity\ShipmentPackageInterface
   */
  protected $currentPackage;

  /**
   * The shipment packager.
   *
   * @var \Drupal\commerce_packaging\ChainShipmentPackagerInterface
   */
  protected $shipmentPackager;

  /**
   * UPSShipment constructor.
   *
   * @param \Drupal\commerce_packaging\ChainShipmentPackagerInterface $shipment_packager
   *   The shipment packager.
   */
  public function __construct(ChainShipmentPackagerInterface $shipment_packager) {
    $this->shipmentPackager = $shipment_packager;
  }

  /**
   * {@inheritDoc}
   */
  public function getShipment(ShipmentInterface $shipment, ShippingMethodInterface $shipping_method) {
    if ($this->shipmentPackager->hasCustomPackaging($shipping_method)) {
      $shipment = $this->shipmentPackager->packageShipment($shipment, $shipping_method);
    }
    return parent::getShipment($shipment, $shipping_method);
  }

  /**
   * {@inheritDoc}
   */
  protected function setPackage(APIShipment $api_shipment) {
    /** @var \Drupal\commerce_packaging\Entity\ShipmentPackageInterface $packages */
    $packages = $this->shipment->get('packages')->referencedEntities();
    foreach ($packages as $package) {
      $this->currentPackage = $package;
      $api_package = new UPSPackage();

      $this->setDimensions($api_package);
      $this->setWeight($api_package);
      $this->setPackagingType($api_package);

      $api_shipment->addPackage($api_package);
    }

    $api_shipment->setNumOfPiecesInShipment((string)count($packages));
  }

  /**
   * {@inheritDoc}
   */
  protected function getPackageType() {
    if (!$this->currentPackage) {
      return parent::getPackageType();
    }

    return $this->currentPackage->getPackageType();
  }

  /**
   * {@inheritDoc}
   */
  public function setWeight(UPSPackage $ups_package) {
    if (!$this->currentPackage) {
      parent::setWeight($ups_package);
      return;
    }

    $weight = $this->currentPackage->getWeight()->convert($this->getValidWeightUnit());
    $ups_package_weight = new PackageWeight();
    $ups_package_weight->setWeight($weight->getNumber());
    $ups_package_weight->setUnitOfMeasurement($this
      ->setUnitOfMeasurement($this
        ->getUnitOfMeasure($weight->getUnit()
        ))
    );

    $ups_package->setPackageWeight($ups_package_weight);
  }

}
