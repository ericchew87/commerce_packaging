<?php

namespace Drupal\commerce_packaging;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class must be module name in camel_case + ServiceProvider to work properly.
 */
class CommercePackagingServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Swap the packer manager so that global packaging can be done after packers run.
    $definition = $container->getDefinition('commerce_shipping.packer_manager');
    $definition->setClass(PackerManager::class);
    $definition->addArgument(new Reference('commerce_packaging.chain_shipment_packager'));
  }

}
