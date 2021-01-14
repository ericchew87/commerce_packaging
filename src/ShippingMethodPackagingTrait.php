<?php

namespace Drupal\commerce_packaging;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Entity\ShipmentType;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodInterface;
use Drupal\Core\Form\FormStateInterface;

trait ShippingMethodPackagingTrait {

  /**
   * The shipment packager.
   *
   * @var \Drupal\commerce_packaging\ShipmentPackagerManager
   */
  protected $shipmentPackager;

  /**
   * The inline form manager.
   *
   * @var \Drupal\commerce\InlineFormManager
   */
  protected $inlineFormManager;

  /**
   * Builds the packaging configuration form.
   *
   * @param array $form
   *   The parent form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodInterface $shipping_method_plugin
   *   The shipping method plugin.
   *
   * @return array
   *   The parent form with the packaging configuration added.
   */
  public function buildPackagingConfigurationForm(array $form, FormStateInterface $form_state, ShippingMethodInterface $shipping_method_plugin) {
    $packager_configuration = !empty($this->configuration['packagers']) ? $this->configuration['packagers'] : [];

    $form['packager_settings'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Packager Settings'),
    ];

    $form['packager_settings']['use_global_settings'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use global packaging settings.'),
      '#default_value' => empty($packager_configuration)
    ];

    $inline_form = $this->inlineFormManager->createInstance('packager_settings', $packager_configuration);
    $form['packager_settings']['form'] = [
      '#parents' => ['packager_settings'],
      '#inline_form' => $inline_form,
      '#states' => [
        'invisible' => [
          ':input[name="plugin[0][target_plugin_configuration][ups][packager_settings][use_global_settings]"]' => ['checked' => TRUE]
        ]
      ]
    ];
    $form['packager_settings']['form'] = $inline_form->buildInlineForm($form['packager_settings']['form'], $form_state);

    return $form;
  }

  /**
   * Submit handler for the packaging configuration form.
   *
   * @param array $form
   *   The parent form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodInterface $shipping_method_plugin
   *   The shipping method plugin.
   */
  public function submitPackagingConfigurationForm(array $form, FormStateInterface $form_state, ShippingMethodInterface $shipping_method_plugin) {
    if (!$form_state->getErrors()) {
      $use_global_settings = $form_state->getValue(array_merge($form['#parents'], ['packager_settings', 'use_global_settings']));
      if (!$use_global_settings) {
        /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\InlineFormInterface $inline_form */
        $inline_form = $form['packager_settings']['form']['#inline_form'];
        $configuration = $inline_form->getConfiguration();
        $this->configuration['packagers'] = $configuration;
      }
      else {
        unset($this->configuration['packagers']);
      }

    }
  }

  /**
   * Gets the shipment packager plugins.
   *
   * @param \Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodInterface $shipping_method_plugin
   *   The shipping method plugin.
   *
   * @return \Drupal\commerce_packaging\Plugin\Commerce\ShipmentPackager\ShipmentPackagerInterface[]
   *   The shipment packager plugins.
   */
  public function getPackagers(ShippingMethodInterface $shipping_method_plugin) {
    $packagers = [];

    // Get the global packager settings.
    $configuration = \Drupal::config('commerce_packaging.shipment_packager_settings')->get('packagers');
    // Check if the shipping method has custom packager settings.
    if ($this->hasCustomPackaging($shipping_method_plugin)) {
      $configuration = $shipping_method_plugin->getConfiguration()['packagers'];
    }

    if (!empty($configuration['enabled'])) {
      foreach ($configuration['enabled'] as $plugin_id) {
        if ($this->shipmentPackager->hasDefinition($plugin_id)) {
          $packagers[] = $this->shipmentPackager->createInstance($plugin_id);
        }
      }
    }

    return $packagers;
  }

  /**
   * Clones a shipment and returns it with packaged items.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   * @param \Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodInterface $shipping_method_plugin
   *   The shipping method plugin.
   *
   * @return \Drupal\commerce_shipping\Entity\ShipmentInterface
   *   The cloned shipment with packaged items.
   */
  public function packageShipment(ShipmentInterface $shipment, ShippingMethodInterface $shipping_method_plugin) {
    $shipment_type = ShipmentType::load($shipment->bundle());
    if (!$shipment_type->getThirdPartySetting('commerce_packaging', 'shipment_package_type')) {
      \Drupal::messenger()->addWarning(t('Packagers could not run because because the %type shipment type does not specify a shipment package type', ['%type' => $shipment_type->label()]));
      return $shipment;
    }

    $packagers = $this->getPackagers($shipping_method_plugin);
    if (!empty($packagers)) {
      /** @var \Drupal\commerce_packaging\Plugin\Commerce\ShipmentPackager\ShipmentPackagerInterface $packager */
      foreach ($packagers as $packager) {
        $packager->packageItems($shipment, $shipping_method_plugin);
        if (empty($shipment->getData('unpackaged_items'))) {
          break;
        }
      }
      $items = $shipment->getData('unpackaged_items', []) + $shipment->getData('packaged_items', []);
      $shipment->setItems($items);
    }

    return $shipment;
  }

  /**
   * Gets whether the shipping method has custom packaging settings.
   *
   * @param \Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodInterface $shipping_method_plugin
   *   The shipping method plugin.
   *
   * @return bool
   *   TRUE if the shipping method has custom packaging settings, FALSE otherwise.
   */
  public function hasCustomPackaging(ShippingMethodInterface $shipping_method_plugin) {
    $configuration = $shipping_method_plugin->getConfiguration();
    return !empty($configuration['packagers']);
  }

}
