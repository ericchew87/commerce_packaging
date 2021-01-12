<?php

namespace Drupal\commerce_packaging\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure the shipment packager settings.
 */
class ShipmentPackagerSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_packaging_shipment_packager_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['commerce_packaging.shipment_packager_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('commerce_packaging.shipment_packager_settings');

    $shipment_packager_manager = \Drupal::service('plugin.manager.commerce_shipment_packager');
    $packagers = $shipment_packager_manager->getDefinitions();

    $form['#fields'] = array_keys($packagers);

    $form['fields'] = [
      '#type' => 'field_ui_table',
      '#header' => $this->getTableHeader(),
      '#regions' => $this->getRegions(),
      '#attributes' => [
        'class' => ['field-ui-overview'],
        'id' => 'field-display-overview',
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'field-weight',
        ],
        [
          'action' => 'match',
          'relationship' => 'parent',
          'group' => 'field-parent',
          'subgroup' => 'field-parent',
          'source' => 'field-name',
        ],
        [
          'action' => 'match',
          'relationship' => 'parent',
          'group' => 'field-region',
          'subgroup' => 'field-region',
          'source' => 'field-name',
        ],
      ],
    ];

    foreach ($packagers as $packager_id => $plugin_definition) {
      $form['fields'][$packager_id] = $this->buildPluginRow($plugin_definition, $form, $form_state);
    }

    $form['#attached']['library'][] = 'field_ui/drupal.field_ui';

    return $form;
  }

  /**
   * Returns an array containing the table headers.
   *
   * @return array
   *   The table header.
   */
  protected function getTableHeader() {
    return [
      $this->t('Field'),
      $this->t('Weight'),
      $this->t('Parent'),
      $this->t('Region'),
    ];
  }


  /**
   * Get the regions needed to create the overview form.
   *
   * @return array
   *   Example usage:
   *   @code
   *     return array(
   *       'content' => array(
   *         // label for the region.
   *         'title' => $this->t('Content'),
   *         // Indicates if the region is visible in the UI.
   *         'invisible' => TRUE,
   *         // A message to indicate that there is nothing to be displayed in
   *         // the region.
   *         'message' => $this->t('No field is displayed.'),
   *       ),
   *     );
   *   @endcode
   */
  protected function getRegions() {
    return [
      'content' => [
        'title' => $this->t('Content'),
        'invisible' => TRUE,
        'message' => $this->t('No plugin is enabled.'),
      ],
      'hidden' => [
        'title' => $this->t('Disabled', [], ['context' => 'Plural']),
        'message' => $this->t('No plugin is disabled.'),
      ],
    ];
  }

  /**
   * Returns an associative array of all regions.
   *
   * @return array
   *   An array containing the region options.
   */
  public function getRegionOptions() {
    $options = [];
    foreach ($this->getRegions() as $region => $data) {
      $options[$region] = $data['title'];
    }
    return $options;
  }

  /**
   * Returns the region to which a row in the display overview belongs.
   *
   * @param array $row
   *   The row element.
   *
   * @return string|null
   *   The region name this row belongs to.
   */
  public function getRowRegion(&$row) {
    $regions = $this->getRegions();
    if (!isset($regions[$row['region']['#value']])) {
      $row['region']['#value'] = 'hidden';
    }
    return $row['region']['#value'];
  }

  protected function buildPluginRow(array $plugin_definition, array $form, FormStateInterface $form_state) {
    $field_name = $plugin_definition['id'];
    $label = $plugin_definition['label'];

    $regions = array_keys($this->getRegions());
    $field_row = [
      '#attributes' => ['class' => ['draggable', 'tabledrag-leaf']],
      '#row_type' => 'field',
      '#region_callback' => [$this, 'getRowRegion'],
      'human_name' => [
        '#plain_text' => $label,
      ],
      'weight' => [
        '#type' => 'textfield',
        '#title' => $this->t('Weight for @title', ['@title' => $label]),
        '#title_display' => 'invisible',
        '#default_value' => '0',
        '#size' => 3,
        '#attributes' => ['class' => ['field-weight']],
      ],
      'parent_wrapper' => [
        'parent' => [
          '#type' => 'select',
          '#title' => $this->t('Label display for @title', ['@title' => $label]),
          '#title_display' => 'invisible',
          '#options' => array_combine($regions, $regions),
          '#empty_value' => '',
          '#attributes' => ['class' => ['js-field-parent', 'field-parent']],
          '#parents' => ['fields', $field_name, 'parent'],
        ],
        'hidden_name' => [
          '#type' => 'hidden',
          '#default_value' => $field_name,
          '#attributes' => ['class' => ['field-name']],
        ],
      ],
      'region' => [
        '#type' => 'select',
        '#title' => $this->t('Region for @title', ['@title' => $label]),
        '#title_display' => 'invisible',
        '#options' => $this->getRegionOptions(),
        '#default_value' => 'hidden',
        '#attributes' => ['class' => ['field-region']],
      ],
    ];

    return $field_row;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $config = $this->config('commerce_packaging.shipment_packager_settings');



    parent::submitForm($form, $form_state);
  }

}
