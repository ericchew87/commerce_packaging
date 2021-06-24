<?php

namespace Drupal\commerce_packaging\Form;

use Drupal\commerce\InlineFormManager;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class PackagingStrategyForm.
 */
class PackagingStrategyForm extends EntityForm {

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

  public function __construct(InlineFormManager $inline_form_manager, PackageTypeManagerInterface $package_type_manager) {
    $this->inlineFormManager = $inline_form_manager;
    $this->packageTypeManager = $package_type_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.commerce_inline_form'),
      $container->get('plugin.manager.commerce_package_type')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $packaging_strategy = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $packaging_strategy->label(),
      '#description' => $this->t("Label for the Packaging strategy."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $packaging_strategy->id(),
      '#machine_name' => [
        'exists' => '\Drupal\commerce_packaging\Entity\PackagingStrategy::load',
      ],
      '#disabled' => !$packaging_strategy->isNew(),
    ];

    $package_types = $this->packageTypeManager->getDefinitions();
    $package_types = array_map(function ($package_type) {
      return $package_type['label'];
    }, $package_types);

    $form['default_package_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Default package type'),
      '#options' => $package_types,
      '#default_value' => $packaging_strategy->get('default_package_type'),
      '#required' => TRUE,
      '#access' => count($package_types) > 1,
    ];

    $packagers = $packaging_strategy->get('packagers');
    $inline_form = $this->inlineFormManager->createInstance('commerce_packager_settings', $packagers);
    $form['packagers'] = [
      '#parents' => [],
      '#inline_form' => $inline_form,
    ];
    $form['packagers'] = $inline_form->buildInlineForm($form['packagers'], $form_state);

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $packaging_strategy = $this->entity;

    /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\InlineFormInterface $inline_form */
    $inline_form = $form['packagers']['#inline_form'];
    $configuration = $inline_form->getConfiguration();
    $packaging_strategy->set('packagers', $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $packaging_strategy = $this->entity;
    $status = $packaging_strategy->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Packaging strategy.', [
          '%label' => $packaging_strategy->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Packaging strategy.', [
          '%label' => $packaging_strategy->label(),
        ]));
    }
    $form_state->setRedirectUrl($packaging_strategy->toUrl('collection'));
  }

}
