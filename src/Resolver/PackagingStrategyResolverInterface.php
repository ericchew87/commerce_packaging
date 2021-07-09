<?php

namespace Drupal\commerce_packaging\Resolver;

use Drupal\commerce\Context;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Entity\ShippingMethodInterface;

/**
 * Defines the interface for packaging strategy resolvers.
 */
interface PackagingStrategyResolverInterface {


  public function resolve(ShippingMethodInterface $shipping_method, ShipmentInterface $shipment);

}
