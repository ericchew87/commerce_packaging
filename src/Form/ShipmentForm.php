<?php


namespace Drupal\commerce_packaging\Form;


use Drupal\commerce_shipping\Form\ShipmentForm as ShipmentFormBase;
use Drupal\Core\Form\FormStateInterface;

class ShipmentForm extends ShipmentFormBase {

  protected function addShippingItems(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $this->entity;
    // If the shipment has packages then the ShipmentItems were already updated by the packagers.
    if ($shipment->hasField('packages') && !$shipment->get('packages')->isEmpty()) {
      return;
    }

    parent::addShippingItems($form, $form_state);
  }

}
