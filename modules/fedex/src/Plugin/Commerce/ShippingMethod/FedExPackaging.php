<?php

namespace Drupal\commerce_packaging_fedex\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_fedex\FedExPluginManager;
use Drupal\commerce_fedex\FedExRequestInterface;
use Drupal\commerce_fedex\Plugin\Commerce\ShippingMethod\FedEx;
use Drupal\commerce_packaging\ShipmentPackagerManager;
use Drupal\commerce_packaging\ShippingMethodPackagingTrait;
use Drupal\commerce_price\RounderInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\state_machine\WorkflowManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class FedExPackaging extends FedEx {

  use ShippingMethodPackagingTrait;

  /**
   * Constructs a new FedExPackaging object.
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
   * @param \Drupal\commerce_fedex\FedExPluginManager $fedex_service_manager
   *   The FedEx Plugin Manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The Event Dispatcher.
   * @param \Drupal\commerce_fedex\FedExRequestInterface $fedex_request
   *   The Fedex Request Service.
   * @param \Psr\Log\LoggerInterface $watchdog
   *   Commerce Fedex Logger Channel.
   * @param \Drupal\commerce_price\RounderInterface $rounder
   *   The price rounder.
   * @param \Drupal\commerce_packaging\ShipmentPackagerManager $shipment_packager
   *   The shipment packager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PackageTypeManagerInterface $package_type_manager, WorkflowManagerInterface $workflow_manager, FedExPluginManager $fedex_service_manager, EventDispatcherInterface $event_dispatcher, FedExRequestInterface $fedex_request, LoggerInterface $watchdog, RounderInterface $rounder, ShipmentPackagerManager $shipment_packager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $package_type_manager, $workflow_manager, $fedex_service_manager, $event_dispatcher, $fedex_request, $watchdog, $rounder);

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
      $container->get('plugin.manager.commerce_fedex_service'),
      $container->get('event_dispatcher'),
      $container->get('commerce_fedex.fedex_request'),
      $container->get('logger.channel.commerce_fedex'),
      $container->get('commerce_price.rounder'),
      $container->get('plugin.manager.commerce_shipment_packager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form = $this->buildPackagingConfigurationForm($form, $form_state, $this);
    $form['options']['packaging'] = [
      '#type' => 'value',
      '#value' => $this->configuration['options']['packaging'],
      'notice' => ['#markup' => $this->t('<p><strong>NOTICE:</strong> Packaging strategy is being overridden by Commerce Packaging FedEx module.</p>')]
    ];
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
  protected function getRequestedPackageLineItems(ShipmentInterface $shipment) {
    $shipment = $this->packageShipment($shipment, $this);
    return $this->getRequestedPackageLineItemsIndividual($shipment);
  }

}
