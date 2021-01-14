<?php


namespace Drupal\commerce_packaging;


use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

class ChainShipmentPackager implements ChainShipmentPackagerInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The shipment packager config settings.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * @var \Drupal\commerce_packaging\ShipmentPackagerPluginManager
   *   The shipment packager plugin manager.
   */
  protected $shipmentPackagerPluginManager;

  /**
   * The package type manager.
   *
   * @var \Drupal\commerce_shipping\PackageTypeManagerInterface
   */
  protected $packageTypeManager;

  /**
   * ShipmentPackager constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\commerce_packaging\ShipmentPackagerPluginManager $shipment_packager_plugin_manager
   *   The shipment packager plugin manager.
   * @param \Drupal\commerce_shipping\PackageTypeManagerInterface $package_type_manager
   *   The package type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger, ConfigFactoryInterface $config_factory, ShipmentPackagerPluginManager $shipment_packager_plugin_manager, PackageTypeManagerInterface $package_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->config = $config_factory->get('commerce_packaging.shipment_packager_settings');
    $this->shipmentPackagerPluginManager = $shipment_packager_plugin_manager;
    $this->packageTypeManager = $package_type_manager;
  }

  /**
   * {@inheritDoc}
   */
  public function getEnabledPackagers(ShippingMethodInterface $shipping_method_plugin = NULL) {
    $packagers = [];

    // Get the global packager settings.
    $configuration = $this->config->get('packagers');
    // If a shipping method was provided, check if it has custom packaging settings.
    if ($shipping_method_plugin && $this->hasCustomPackaging($shipping_method_plugin)) {
      $configuration = $shipping_method_plugin->getConfiguration()['packagers'];
    }

    if (!empty($configuration['enabled'])) {
      foreach ($configuration['enabled'] as $plugin_id) {
        if ($this->shipmentPackagerPluginManager->hasDefinition($plugin_id)) {
          $packagers[] = $this->shipmentPackagerPluginManager->createInstance($plugin_id);
        }
      }
    }

    return $packagers;
  }

  /**
   * {@inheritDoc}
   */
  public function packageShipment(ShipmentInterface $shipment, ShippingMethodInterface $shipping_method_plugin = NULL) {
    // Ensure the packagers know what type of shipment packages to create.
    $shipment_type = $this->entityTypeManager->getStorage('commerce_shipment_type')->load($shipment->bundle());
    if (!$shipment_type->getThirdPartySetting('commerce_packaging', 'shipment_package_type')) {
      $this->messenger->addWarning($this->t('Packagers could not run because because the %type shipment type does not specify a shipment package type', ['%type' => $shipment_type->label()]));
      return $shipment;
    }

    // Ensure the shipment has a package type so that packagers have a default package type to use if they need it.
    if (!$shipment->getPackageType()) {
      $default_package_type_id = $this->config->get('default_package_type');
      $default_package_type = $this->packageTypeManager->createInstance($default_package_type_id);
      if ($shipping_method_plugin) {
        $default_package_type = $shipping_method_plugin->getDefaultPackageType();
      }
      if (!$default_package_type) {
        $this->messenger->addWarning($this->t('Packagers could not run because because no default shipment type was provided in the packager settings'));
        return $shipment;
      }
      $shipment->setPackageType($default_package_type);
    }

    $packagers = $this->getEnabledPackagers($shipping_method_plugin);
    if (!empty($packagers)) {
      $shipment->set('packages', []);
      $shipment->setData('unpackaged_items', $shipment->getItems());
      $shipment->setData('packaged_items', []);
      foreach ($packagers as $packager) {
        $packager->packageItems($shipment);
        if (empty($shipment->getData('unpackaged_items'))) {
          break;
        }
      }
      $items = $shipment->getData('unpackaged_items') + $shipment->getData('packaged_items');
      $shipment->setItems($items);
    }

    return $shipment;
  }

  /**
   * {@inheritDoc}
   */
  public function hasCustomPackaging(ShippingMethodInterface $shipping_method_plugin) {
    $configuration = $shipping_method_plugin->getConfiguration();
    return !empty($configuration['packagers']);
  }

}
