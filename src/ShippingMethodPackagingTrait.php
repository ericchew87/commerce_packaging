<?php

namespace Drupal\commerce_packaging;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
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
   * Builds the packaging configuration form.
   *
   * @param array $form
   *   The parent form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The parent form with the packaging configuration added.
   */
  public function buildPackagingConfigurationForm(array $form, FormStateInterface $form_state, ShippingMethodInterface $shipping_method_plugin) {
    $shipment_packager_manager = \Drupal::service('plugin.manager.commerce_shipment_packager');
    $packagers = $shipment_packager_manager->getDefinitions();

    $plugin_configuration = $shipping_method_plugin->getConfiguration();
    // sort packagers based on their weight so they are ordered correctly in the tabledrag element.
    if (!empty($plugin_configuration['packagers'])) {
      uasort($packagers, function($a, $b) use($plugin_configuration) {
        if (!empty($plugin_configuration['packagers'][$a['id']]) && !empty($plugin_configuration['packagers'][$b['id']])) {
          return ($plugin_configuration['packagers'][$a['id']]['weight'] < $plugin_configuration['packagers'][$b['id']]['weight']) ? -1 : 1;
        }
        return 0;
      });
    }

    $form['packager_config'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => t('Packager Configuration'),
      '#weight' => 2,
    ];

    $form['packager_config']['packagers'] = [
      '#type' => 'table',
      '#header' => [
        t('Enabled'),
        t('Name'),
        t('Description'),
        t('Weight'),
      ],
      '#empty' => t('No shipment packager plugins have been defined.'),
      '#attributes' => [
        'id' => 'packager-config-table',
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'table-sort-weight',
        ],
      ],
    ];

    $delta = 0;
    foreach ($packagers as $packager) {

      $weight = !empty($plugin_configuration['packagers'][$packager['id']]['weight']) ?
        $plugin_configuration['packagers'][$packager['id']]['weight'] : $delta;
      $enabled = !empty($plugin_configuration['packagers'][$packager['id']]['enabled']) ?
        $plugin_configuration['packagers'][$packager['id']]['enabled'] : FALSE;

      $form['packager_config']['packagers'][$packager['id']]['#attributes']['class'][] = 'draggable';
      $form['packager_config']['packagers'][$packager['id']]['#weight'] = $weight;
      $form['packager_config']['packagers'][$packager['id']]['enabled'] = [
        '#type' => 'checkbox',
        '#title' => t('Enabled'),
        '#title_display' => 'invisible',
        '#default_value' => $enabled,
      ];
      $form['packager_config']['packagers'][$packager['id']]['name'] = [
        '#markup' => $packager['label'],
      ];
      $form['packager_config']['packagers'][$packager['id']]['description'] = [
        '#markup' => $packager['description'],
      ];
      $form['packager_config']['packagers'][$packager['id']]['weight'] = [
        '#type' => 'weight',
        '#title' => t('Weight for @title', ['@title' => $packager['label']]),
        '#title_display' => 'invisible',
        '#default_value' => $weight,
        // Classify the weight element for #tabledrag.
        '#attributes' => ['class' => ['table-sort-weight']],
      ];
      $delta++;
    }

    return $form;
  }

  /**
   * Submit handler for the packaging configuration form.
   *
   * @param array $form
   *   The parent form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitPackagingConfigurationForm(array $form, FormStateInterface $form_state, ShippingMethodInterface $shipping_method_plugin) {
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['packagers'] = $values['packager_config']['packagers'];
    }
  }

  /**
   * Gets the shipment packager plugins.
   *
   * @return \Drupal\commerce_packaging\Plugin\Commerce\ShipmentPackager\ShipmentPackagerInterface[]
   *   The shipment packager plugins.
   */
  public function getPackagers(ShippingMethodInterface $shipping_method_plugin) {
    $packagers = [];
    $configuration = $shipping_method_plugin->getConfiguration();
    if (!empty($configuration['packagers'])) {
      foreach ($configuration['packagers'] as $key => $values) {
        if ($this->shipmentPackager->hasDefinition($key) && $values['enabled']) {
          $packagers[] = $this->shipmentPackager->createInstance($key);
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
   *
   * @return \Drupal\commerce_shipping\Entity\ShipmentInterface
   *   The cloned shipment with packaged items.
   */
  public function packageShipment(ShipmentInterface $shipment, ShippingMethodInterface $shipping_method_plugin) {
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

}
