<?php

namespace Drupal\commerce_packaging_ups;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class must be module name in camel_case + ServiceProvider to work properly.
 */
class CommercePackagingUpsServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($definition = $container->getDefinition('commerce_ups.ups_shipment')) {
      $definition->setClass(UPSShipment::class);
      $definition->addArgument(new Reference('commerce_packaging.chain_shipment_packager'));
    }
  }

}
