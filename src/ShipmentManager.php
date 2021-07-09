<?php

namespace Drupal\commerce_packaging;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Event\ShippingEvents;
use Drupal\commerce_shipping\Event\ShippingRatesEvent;
use Drupal\commerce_shipping\ShipmentManager as ShipmentManagerBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ShipmentManager extends ShipmentManagerBase {

  /**
   * The shipment packager.
   *
   * @var \Drupal\commerce_packaging\ChainShipmentPackagerInterface
   */
  protected $shipmentPackager;

  /**
   * Constructs a new ShipmentManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\commerce_packaging\ChainShipmentPackagerInterface $shipment_packager
   *   The shipment packager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $entity_repository, EventDispatcherInterface $event_dispatcher, LoggerInterface $logger, ChainShipmentPackager $shipment_packager) {
    parent::__construct($entity_type_manager, $entity_repository, $event_dispatcher, $logger);
    $this->shipmentPackager = $shipment_packager;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateRates(ShipmentInterface $shipment) {
    $all_rates = [];
    /** @var \Drupal\commerce_shipping\ShippingMethodStorageInterface $shipping_method_storage */
    $shipping_method_storage = $this->entityTypeManager->getStorage('commerce_shipping_method');
    $shipping_methods = $shipping_method_storage->loadMultipleForShipment($shipment);
    foreach ($shipping_methods as $shipping_method) {
      /** @var \Drupal\commerce_shipping\Entity\ShippingMethodInterface $shipping_method */
      $shipping_method = $this->entityRepository->getTranslationFromContext($shipping_method);
      $shipping_method_plugin = $shipping_method->getPlugin();
      try {
        $shipment = $this->shipmentPackager->packageShipment($shipping_method, $shipment);
        $rates = $shipping_method_plugin->calculateRates($shipment);
      }
      catch (\Exception $exception) {
        $this->logger->error('Exception occurred when calculating rates for @name: @message', [
          '@name' => $shipping_method->getName(),
          '@message' => $exception->getMessage(),
        ]);
        continue;
      }
      // Allow the rates to be altered via code.
      $event = new ShippingRatesEvent($rates, $shipping_method, $shipment);
      $this->eventDispatcher->dispatch(ShippingEvents::SHIPPING_RATES, $event);
      $rates = $event->getRates();

      $rates = $this->sortRates($rates);
      foreach ($rates as $rate) {
        $all_rates[$rate->getId()] = $rate;
      }
    }

    return $all_rates;
  }

}
