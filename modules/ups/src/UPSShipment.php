<?php

namespace Drupal\commerce_packaging_ups;

use Drupal\commerce_ups\UPSShipment as UPSShipmentBase;
use Ups\Entity\Package as UPSPackage;
use Ups\Entity\PackageWeight;
use Ups\Entity\Shipment as APIShipment;

class UPSShipment extends UPSShipmentBase {

  /**
   * The current package being processed.
   *
   * @var \Drupal\commerce_packaging\ProposedShipmentPackage
   */
  protected $currentPackage;

  /**
   * {@inheritDoc}
   */
  protected function setPackage(APIShipment $api_shipment) {
    $proposed_shipment_packages_data = $this->shipment->getData('proposed_shipment_packages');

    // Workaround to get the shipping method since it is a protected property.
    $reflection = new \ReflectionProperty($this->shippingMethod, 'parentEntity');
    $reflection->setAccessible(TRUE);
    $shipping_method = $reflection->getValue($this->shippingMethod);

    if (!empty($proposed_shipment_packages_data[$shipping_method->id()])) {
      $packages = $proposed_shipment_packages_data[$shipping_method->id()];
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
