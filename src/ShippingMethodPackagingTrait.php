<?php

namespace Drupal\commerce_packaging;

use Drupal\Core\Form\FormStateInterface;

trait ShippingMethodPackagingTrait {

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
   *
   * @return array
   *   The parent form with the packaging configuration added.
   */
  public function buildPackagingConfigurationForm(array $form, FormStateInterface $form_state) {
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
   */
  public function submitPackagingConfigurationForm(array $form, FormStateInterface $form_state) {
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

}
