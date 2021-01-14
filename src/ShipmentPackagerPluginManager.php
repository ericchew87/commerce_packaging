<?php

namespace Drupal\commerce_packaging;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages discovery and instantiation of shipment packager plugins.
 *
 * @see \Drupal\commerce_packaging\Annotation\CommerceShipmentPackager
 * @see plugin_api
 */
class ShipmentPackagerPluginManager extends DefaultPluginManager {

  /**
   * Constructs a new ShipmentPackagerManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/Commerce/ShipmentPackager',
      $namespaces,
      $module_handler,
      'Drupal\commerce_packaging\Plugin\Commerce\ShipmentPackager\ShipmentPackagerInterface',
      'Drupal\commerce_packaging\Annotation\CommerceShipmentPackager'
    );
    $this->setCacheBackend($cache_backend, 'commerce_shipment_packager_plugins');
  }

}