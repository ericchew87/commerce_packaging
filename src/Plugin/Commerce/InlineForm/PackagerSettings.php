<?php

namespace Drupal\commerce_packaging\Plugin\Commerce\InlineForm;

use Drupal\commerce\Plugin\Commerce\InlineForm\InlineFormBase;
use Drupal\commerce_packaging\ShipmentPackagerPluginManager;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an inline form for managing a packager settings.
 *
 *
 * @CommerceInlineForm(
 *   id = "packager_settings",
 *   label = @Translation("Packager Settings"),
 * )
 */
class PackagerSettings extends InlineFormBase {

  /**
   * The shipment packager manager.
   *
   * @var \Drupal\commerce_packaging\ShipmentPackagerPluginManager
   */
  protected $shipmentPackager;

  /**
   * Constructs a new PackagerSettings object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_packaging\ShipmentPackagerPluginManager $shipment_packager
   *   The shipment packager manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ShipmentPackagerPluginManager $shipment_packager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->shipmentPackager = $shipment_packager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.commerce_shipment_packager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'enabled' => [],
      'disabled' => [],
    ];
  }

  public function buildInlineForm(array $inline_form, FormStateInterface $form_state) {
    $inline_form = parent::buildInlineForm($inline_form, $form_state);
    $packagers = $this->shipmentPackager->getDefinitions();

    $inline_form['fields'] = [
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
      $inline_form['fields'][$packager_id] = $this->buildPluginRow($plugin_definition, $inline_form, $form_state);
    }
    $inline_form['#attached']['library'][] = 'field_ui/drupal.field_ui';


    return $inline_form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitInlineForm(array &$inline_form, FormStateInterface $form_state) {
    parent::submitInlineForm($inline_form, $form_state);
    $values = $form_state->getValue($inline_form['#parents']);

    $packagers = $this->defaultConfiguration();
    foreach ($values['fields'] as $plugin_id => $packager) {
      if ($packager['region'] !== 'hidden') {
        $packagers['enabled'][$packager['weight']] = $plugin_id;
      }
      else {
        $packagers['disabled'][$packager['weight']] = $plugin_id;
      }
    }
    $this->setConfiguration($packagers);
  }

  /**
   * Builds the table row structure for a single packager plugin.
   *
   * @param array $plugin_definition
   *   The plugin definition.
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   A table row array.
   */
  protected function buildPluginRow(array $plugin_definition, array $form, FormStateInterface $form_state) {
    $field_name = $plugin_definition['id'];
    $label = $plugin_definition['label'];

    $regions = array_keys($this->getRegions());
    $field_row = [
      '#attributes' => ['class' => ['draggable', 'tabledrag-leaf']],
      '#row_type' => 'field',
      '#region_callback' => [$this, 'getRowRegion'],
      '#js_settings' => [
        'rowHandler' => 'field',
      ],
      'human_name' => [
        '#plain_text' => $label,
      ],
      'description' => [
        '#markup' => $plugin_definition['description'],
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
        '#default_value' => $this->isPackagerEnabled($field_name) ? 'content' : 'hidden',
        '#attributes' => ['class' => ['field-region']],
      ],
    ];

    return $field_row;
  }

  /**
   * Gets whether the given packager plugin_id is enabled.
   *
   * @param string $plugin_id
   *   The packager plugin id.
   * @return bool
   *   TRUE if the packager plugin is enabled, FALSE otherwise.
   */
  protected function isPackagerEnabled(string $plugin_id) {
    return in_array($plugin_id, $this->configuration['enabled']);
  }

  /**
   * Returns an array containing the table headers.
   *
   * @return array
   *   The table header.
   */
  protected function getTableHeader() {
    return [
      $this->t('Plugin'),
      $this->t('Description'),
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

}
