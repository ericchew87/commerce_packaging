<?php

namespace Drupal\commerce_packaging\Form;

use Drupal\commerce\InlineFormManager;
use Drupal\commerce_packaging\ShipmentPackagerManager;
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
   * ShipmentPackagerSettings constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\commerce\InlineFormManager $inline_form_manager
   *   The inline form manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, InlineFormManager $inline_form_manager) {
    parent::__construct($config_factory);
    $this->inlineFormManager = $inline_form_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.commerce_inline_form')
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
    $packager_configuration = $this->settings->get('packagers') ?: [];

    $inline_form = $this->inlineFormManager->createInstance('packager_settings', $packager_configuration);
    $form['packager_settings'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Packager Settings'),
      'form' => [
        '#parents' => ['packager_settings'],
        '#inline_form' => $inline_form,
      ],
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
    $this->settings->save();
  }

}
