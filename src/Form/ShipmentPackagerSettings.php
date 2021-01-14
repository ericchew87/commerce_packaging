<?php

namespace Drupal\commerce_packaging\Form;

use Drupal\commerce\InlineFormManager;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure the shipment packager settings.
 */
class ShipmentPackagerSettings extends ConfigFormBase {

  /**
   * @var \Drupal\Core\Config\Config
   */
  protected $settings;

  /**
   * The inline form manager.
   *
   * @var \Drupal\commerce\InlineFormManager
   */
  protected $inlineFormManager;

  /**
   * The package type manager.
   *
   * @var \Drupal\commerce_shipping\PackageTypeManagerInterface
   */
  protected $packageTypeManager;

  /**
   * ShipmentPackagerSettings constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\commerce\InlineFormManager $inline_form_manager
   *   The inline form manager.
   * @param \Drupal\commerce_shipping\PackageTypeManagerInterface $package_type_manager
   *   The package type manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, InlineFormManager $inline_form_manager, PackageTypeManagerInterface $package_type_manager) {
    parent::__construct($config_factory);
    $this->inlineFormManager = $inline_form_manager;
    $this->packageTypeManager = $package_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.commerce_inline_form'),
      $container->get('plugin.manager.commerce_package_type')
    );
  }

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
    $this->settings = $this->config('commerce_packaging.shipment_packager_settings');

    $package_types = $this->packageTypeManager->getDefinitions();
    $package_types = array_map(function ($package_type) {
      return $package_type['label'];
    }, $package_types);

    $form['packager_settings'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Packager Settings'),
    ];

    $form['packager_settings']['default_package_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Default package type'),
      '#options' => $package_types,
      '#default_value' => $this->settings->get('default_package_type'),
      '#required' => TRUE,
      '#access' => count($package_types) > 1,
    ];

    $packager_configuration = $this->settings->get('packagers') ?: [];
    $inline_form = $this->inlineFormManager->createInstance('packager_settings', $packager_configuration);
    $form['packager_settings']['form'] = [
      '#parents' => ['packager_settings'],
      '#inline_form' => $inline_form,
    ];
    $form['packager_settings']['form'] = $inline_form->buildInlineForm($form['packager_settings']['form'], $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\InlineFormInterface $inline_form */
    $inline_form = $form['packager_settings']['form']['#inline_form'];
    $configuration = $inline_form->getConfiguration();
    $this->settings->set('packagers', $configuration);
    $this->settings->set('default_package_type', $form_state->getValue('default_package_type'));
    $this->settings->save();
  }

}
