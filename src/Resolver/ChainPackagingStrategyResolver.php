<?php

namespace Drupal\commerce_packaging\Resolver;

use Drupal\commerce\Context;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Entity\ShippingMethodInterface;

/**
 * Default implementation of the chain base price resolver.
 */
class ChainPackagingStrategyResolver implements ChainPackagingStrategyResolverInterface {

  /**
   * The resolvers.
   *
   * @var \Drupal\commerce_packaging\Resolver\PackagingStrategyResolverInterface[]
   */
  protected $resolvers = [];

  /**
   * Constructs a new ChainPackagingStrategyResolver object.
   *
   * @param \Drupal\commerce_packaging\Resolver\PackagingStrategyResolverInterface[] $resolvers
   *   The resolvers.
   */
  public function __construct(array $resolvers = []) {
    $this->resolvers = $resolvers;
  }

  /**
   * {@inheritdoc}
   */
  public function addResolver(PackagingStrategyResolverInterface $resolver) {
    $this->resolvers[] = $resolver;
  }

  /**
   * {@inheritdoc}
   */
  public function getResolvers() {
    return $this->resolvers;
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(ShippingMethodInterface $shipping_method, ShipmentInterface $shipment) {
    foreach ($this->resolvers as $resolver) {
      $result = $resolver->resolve($shipping_method, $shipment);
      if ($result) {
        return $result;
      }
    }
  }

}
