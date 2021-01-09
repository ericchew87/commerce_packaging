<?php

namespace Drupal\commerce_packaging_ups\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_packaging\ShipmentPackagerManager;
use Drupal\commerce_packaging\ShippingMethodPackagingTrait;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\commerce_ups\Plugin\Commerce\ShippingMethod\UPS;
use Drupal\commerce_ups\UPSRateRequestInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\state_machine\WorkflowManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class UPSPackaging extends UPS {

  use ShippingMethodPackagingTrait;

  /**
   * Constructs a new UPS object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_shipping\PackageTypeManagerInterface $package_type_manager
   *   The package type manager.
   * @param \Drupal\state_machine\WorkflowManagerInterface $workflow_manager
   *   The workflow manager.
   * @param \Drupal\commerce_ups\UPSRateRequestInterface $ups_rate_request
   *   The rate request service.
   * @param \Drupal\commerce_packaging\ShipmentPackagerManager $shipment_packager
   *   The shipment packager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PackageTypeManagerInterface $package_type_manager, WorkflowManagerInterface $workflow_manager, UPSRateRequestInterface $ups_rate_request, ShipmentPackagerManager $shipment_packager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $package_type_manager, $workflow_manager, $ups_rate_request);

    $this->shipmentPackager = $shipment_packager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.commerce_package_type'),
      $container->get('plugin.manager.workflow'),
      $container->get('commerce_ups.ups_rate_request'),
      $container->get('plugin.manager.commerce_shipment_packager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form = $this->buildPackagingConfigurationForm($form, $form_state, $this);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->submitPackagingConfigurationForm($form, $form_state, $this);
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateRates(ShipmentInterface $shipment) {
    $shipment = clone $shipment;
    return parent::calculateRates($shipment);
  }

  /**
   * {@inheritdoc}
   */
  public function selectRate(ShipmentInterface $shipment, ShippingRate $rate) {
    parent::selectRate($shipment, $rate);
    $this->packageShipment($shipment, $this);
  }

}
