<?php

namespace Drupal\commerce_packaging\Resolver;

/**
 * Runs the added resolvers one by one until one of them returns the packaging strategy.
 *
 * Each resolver in the chain can be another chain, which is why this interface
 * extends the base packaging resolver one.
 */
interface ChainPackagingStrategyResolverInterface extends PackagingStrategyResolverInterface {

  /**
   * Adds a resolver.
   *
   * @param \Drupal\commerce_packaging\Resolver\PackagingStrategyResolverInterface $resolver
   *   The resolver.
   */
  public function addResolver(PackagingStrategyResolverInterface $resolver);

  /**
   * Gets all added resolvers.
   *
   * @return \Drupal\commerce_packaging\Resolver\PackagingStrategyResolverInterface[]
   *   The resolvers.
   */
  public function getResolvers();

}
