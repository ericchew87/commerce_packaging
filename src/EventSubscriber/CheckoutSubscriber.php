<?php


namespace Drupal\commerce_packaging\EventSubscriber;


use Drupal\commerce_checkout\Event\CheckoutCompletionRegisterEvent;
use Drupal\commerce_checkout\Event\CheckoutEvents;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_packaging\ChainShipmentPackagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CheckoutSubscriber implements EventSubscriberInterface {

  /**
   * The shipment packager.
   *
   * @var \Drupal\commerce_packaging\ChainShipmentPackagerInterface
   */
  protected $shipmentPackager;

  /**
   * CheckoutSubscriber constructor.
   *
   * @param \Drupal\commerce_packaging\ChainShipmentPackagerInterface $shipment_packager
   *   The shipment packager.
   */
  public function __construct(ChainShipmentPackagerInterface $shipment_packager) {
    $this->shipmentPackager = $shipment_packager;
  }

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents(){
    return [
      CheckoutEvents::COMPLETION => 'onCheckoutComplete'
    ];
  }

  /**
   * Finalizes packages on checkout completion.
   *
   * @param \Drupal\commerce_order\Event\OrderEvent $event
   *   The order event.
   */
  public function onCheckoutComplete(OrderEvent $event) {
    $order = $event->getOrder();
    if (!$order->hasField('shipments')) {
      return;
    }

    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
    $shipments = $order->get('shipments')->referencedEntities();
    foreach ($shipments as $shipment) {
      $shipment = $this->shipmentPackager->finalizePackages($shipment);
      $shipment->save();
    }
  }

}
