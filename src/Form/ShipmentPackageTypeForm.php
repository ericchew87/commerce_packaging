<?php

namespace Drupal\commerce_packaging\Form;

use Drupal\commerce\Form\CommerceBundleEntityFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeInterface;

class ShipmentPackageTypeForm extends CommerceBundleEntityFormBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\commerce_packaging\Entity\ShipmentPackageTypeInterface $shipment_package_type */
    $shipment_package_type = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $shipment_package_type->label(),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $shipment_package_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\commerce_packaging\Entity\ShipmentPackageType::load',
      ],
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
    ];

    $form = $this->buildTraitForm($form, $form_state);

    return $this->protectBundleIdElement($form);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $this->validateTraitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $this->entity->save();
    $this->submitTraitForm($form, $form_state);

    $this->messenger()->addMessage($this->t('Saved the %label shipment package type.', ['%label' => $this->entity->label()]));
    $form_state->setRedirect('entity.commerce_shipment_package_type.collection');
  }

}
