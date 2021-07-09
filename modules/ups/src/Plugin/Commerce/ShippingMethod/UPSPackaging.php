<?php

namespace Drupal\commerce_packaging_ups\Plugin\Commerce\ShippingMethod;

use Drupal\commerce\EntityHelper;
use Drupal\commerce_packaging\ChainShipmentPackagerInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\commerce_ups\Plugin\Commerce\ShippingMethod\UPS;
use Drupal\commerce_ups\UPSRateRequestInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\state_machine\WorkflowManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class UPSPackaging extends UPS {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The shipment packager.
   *
   * @var \Drupal\commerce_packaging\ChainShipmentPackagerInterface
   */
  protected $shipmentPackager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, PackageTypeManagerInterface $package_type_manager, WorkflowManagerInterface $workflow_manager, UPSRateRequestInterface $ups_rate_request, EntityTypeManagerInterface $entity_type_manager, ChainShipmentPackagerInterface $shipment_packager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $package_type_manager, $workflow_manager, $ups_rate_request);
    $this->entityTypeManager = $entity_type_manager;
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
      $container->get('entity_type.manager'),
      $container->get('commerce_packaging.chain_shipment_packager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
        'commerce_packaging_options' => [
          'packaging_strategy' => NULL,
        ],
      ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $packaging_strategy_storage = $this->entityTypeManager->getStorage('commerce_packaging_strategy');
    $packaging_strategies = $packaging_strategy_storage->loadMultiple();

    $form['commerce_packaging_options'] = [
      '#type' => 'details',
      '#title' => $this->t('Packaging Options'),
      '#open' => TRUE,
    ];

    $form['commerce_packaging_options']['packaging_strategy'] = [
      '#type' => 'select',
      '#title' => $this->t('Packaging Strategy'),
      '#options' => EntityHelper::extractLabels($packaging_strategies),
      '#default_value' => $this->configuration['commerce_packaging_options']['packaging_strategy'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['commerce_packaging_options']['packaging_strategy'] = $values['commerce_packaging_options']['packaging_strategy'];
    }

    parent::submitConfigurationForm($form, $form_state);
  }

}
