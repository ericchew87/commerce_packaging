<?php


namespace Drupal\commerce_packaging\Form;


use Drupal\commerce\InlineFormManager;
use Drupal\commerce_packaging\ChainShipmentPackagerInterface;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\commerce_shipping\ShipmentItem;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\physical\Weight;
use Drupal\physical\WeightUnit;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ShipmentForm extends ContentEntityForm {

  /**
   * The package type manager.
   *
   * @var \Drupal\commerce_shipping\PackageTypeManagerInterface
   */
  protected $packageTypeManager;

  /**
   * The shipment packager.
   *
   * @var \Drupal\commerce_packaging\ChainShipmentPackagerInterface
   */
  protected $shipmentPackager;

  /**
   * The inline form manager.
   *
   * @var \Drupal\commerce\InlineFormManager
   */
  protected $inlineFormManager;

  /**
   * Constructs a new ShipmentForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   * @param \Drupal\commerce_shipping\PackageTypeManagerInterface $package_type_manager
   *   The package type manager.
   * @param \Drupal\commerce_packaging\ChainShipmentPackagerInterface $shipment_packager
   *   The shipment packager.
   * @param \Drupal\commerce\InlineFormManager $inline_form_manager
   *   The inline form manager.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, PackageTypeManagerInterface $package_type_manager, ChainShipmentPackagerInterface $shipment_packager, InlineFormManager $inline_form_manager) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->packageTypeManager = $package_type_manager;
    $this->shipmentPackager = $shipment_packager;
    $this->inlineFormManager = $inline_form_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('plugin.manager.commerce_package_type'),
      $container->get('commerce_packaging.chain_shipment_packager'),
      $container->get('plugin.manager.commerce_inline_form')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $this->ensureOrder();

    $step = $this->getCurrentStep($form, $form_state);

    switch($step) {
      case 'shipping_information':
        $this->buildShippingInformationForm($form, $form_state);
        break;
      case 'package_shipment':
        $this->buildShipmentPackagesBuilderForm($form, $form_state);
        break;
      case 'shipping_method':
        $this->buildShippingMethodForm($form, $form_state);
        break;
    }

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $step = $this->getCurrentStep($form, $form_state);
    if ($step === 'package_shipment') {
      return $this->entity;
    }

    return parent::validateForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $step = $this->getCurrentStep($form, $form_state);

    if ($step === 'package_shipment') {
      /** @var \Drupal\commerce_packaging\Plugin\Commerce\InlineForm\ShipmentPackagesBuilder $inline_form */
      $inline_form = $form['shipment_packages']['#inline_form'];
      $this->setEntity($inline_form->getEntity());
    }

    $this->goToNextStep($form, $form_state);

    $triggering_element = $form_state->getTriggeringElement();
    if ($triggering_element['#name'] === 'previous') {
      return;
    }

    parent::submitForm($form, $form_state);
  }

  protected function getCurrentStep(array $form, FormStateInterface $form_state) {
    $step = $form_state->get('step');
    if (!$step) {
      $step = $this->getDefaultStep($form, $form_state);
      $form_state->set('step', $step);
    }

    return $step;
  }

  protected function getPreviousStep(array $form, FormStateInterface $form_state) {
    return $form_state->get('previous_step');
  }

  protected function getDefaultStep(array $form, FormStateInterface $form_state) {
    return 'shipping_information';
  }

  protected function goToNextStep(array $form, FormStateInterface $form_state) {
    $form_state->setRebuild();
    $current_step = $this->getCurrentStep($form, $form_state);
    $form_state->set('step', $this->getNextStep($form, $form_state));
    $form_state->set('previous_step', $current_step);
  }

  protected function getNextStep(array $form, FormStateInterface $form_state) {
    $next_step = $this->getDefaultStep($form, $form_state);
    $triggering_element = $form_state->getTriggeringElement();

    if ($triggering_element['#name'] === 'previous') {
      return $this->getPreviousStep($form, $form_state);
    }

    switch($form_state->get('step')) {
      case 'shipping_information':
        if ($triggering_element['#name'] === 'package_shipment') {
          $next_step = 'package_shipment';
        }
        else {
          $next_step = 'shipping_method';
        }
        break;
      case 'package_shipment':
        $next_step = 'shipping_method';
        break;
    }

    return $next_step;
  }

  protected function buildShippingInformationForm(array &$form, FormStateInterface $form_state) {
    $form_display = $this->rebuildFormDisplay($form_state);
    $form_display->removeComponent('shipping_method');
    $form_display->buildForm($this->entity, $form, $form_state);
    // The ShippingProfileWidget doesn't output a fieldset because that makes
    // sense in a checkout context, but on the admin form it is clearer for
    // profile fields to be visually grouped.
    $form['shipping_profile']['widget'][0]['#type'] = 'fieldset';

    $form['package_type'] = $this->buildPackageTypeElement();
  }

  protected function buildShippingMethodForm(array &$form, FormStateInterface $form_state) {
    $form['#parents'] = [];
    $form_display = $this->rebuildFormDisplay($form_state);
    $shipping_method_widget = $form_display->getRenderer('shipping_method');
    $items = $this->entity->get('shipping_method');
    $items->filterEmptyItems();
    $form['shipping_method'] = $shipping_method_widget->form($items, $form, $form_state);
  }

  protected function buildShipmentPackagesBuilderForm(array &$form, FormStateInterface $form_state) {
    $inline_form = $this->inlineFormManager->createInstance('shipment_packages_builder', [], $this->entity);
    $form['shipment_packages'] = [
      '#parents' => [],
      '#inline_form' => $inline_form,
    ];
    $form['shipment_packages'] = $inline_form->buildInlineForm($form['shipment_packages'], $form_state);
  }

  protected function rebuildFormDisplay(FormStateInterface $form_state) {
    $form_display = EntityFormDisplay::collectRenderDisplay($this->entity, $this->getOperation());
    $this->setFormDisplay($form_display, $form_state);
    return $form_display;
  }

  protected function buildShippingInformationActions(array $form, FormStateInterface $form_state) {
    $actions = [];
    $actions['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Select Rate'),
      '#submit' => ['::submitForm'],
    ];
    $actions['package_shipment'] = [
      '#type' => 'submit',
      '#value' => $this->t('Package Shipment'),
      '#submit' => ['::submitForm'],
      '#name' => 'package_shipment',
    ];
    if (!$this->entity->isNew() && $this->entity->hasLinkTemplate('delete-form')) {
      $route_info = $this->entity->toUrl('delete-form');
      if ($this->getRequest()->query->has('destination')) {
        $query = $route_info->getOption('query');
        $query['destination'] = $this->getRequest()->query->get('destination');
        $route_info->setOption('query', $query);
      }
      $actions['delete'] = [
        '#type' => 'link',
        '#title' => $this->t('Delete'),
        '#access' => $this->entity->access('delete'),
        '#attributes' => [
          'class' => ['button', 'button--danger'],
        ],
      ];
      $actions['delete']['#url'] = $route_info;
    }

    return $actions;
  }

  protected function buildShippingMethodActions(array $form, FormStateInterface $form_state) {
    $actions = [];
    $actions['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#submit' => ['::submitForm', '::save'],
    ];

    $actions['previous'] = [
      '#type' => 'submit',
      '#value' => $this->t('Previous'),
      '#submit' => ['::submitForm'],
      '#name' => 'previous',
    ];

    return $actions;
  }

  protected function buildShipmentPackagesActions(array $form, FormStateInterface $form_state) {
    $actions = [];
    $actions['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#submit' => ['::submitForm'],
    ];
    $actions['previous'] = [
      '#type' => 'submit',
      '#value' => $this->t('Previous'),
      '#submit' => ['::submitForm'],
      '#name' => 'previous',
    ];
    return $actions;
  }

  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = [];

    $step = $this->getCurrentStep($form, $form_state);
    switch($step) {
      case 'shipping_information':
        $actions = $this->buildShippingInformationActions($form, $form_state);
        break;
      case 'package_shipment':
        $actions = $this->buildShipmentPackagesActions($form, $form_state);
        break;
      case 'shipping_method':
        $actions = $this->buildShippingMethodActions($form, $form_state);
        break;
    }

    return $actions;
  }

  /**
   * Ensures the shipment has an order.
   */
  protected function ensureOrder() {
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $this->entity;

    $order_id = $shipment->get('order_id')->target_id;
    if (!$order_id) {
      $order_id = $this->getRouteMatch()->getRawParameter('commerce_order');
      $shipment->set('order_id', $order_id);
    }
  }

  /**
   * Builds the shipment items element.
   *
   * @return array
   *   The shipment items form element.
   */
  protected function buildShipmentItemsElement() {
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $this->entity;

    $already_on_shipment = $this->getOrderShipmentItemsOnOtherShipments();

    $shipment_item_options = [];
    $shipment_item_defaults = [];
    foreach ($shipment->getItems() as $shipment_item) {
      $shipment_item_id = $shipment_item->getOrderItemId();
      $shipment_item_defaults[$shipment_item_id] = $shipment_item_id;
      $shipment_item_options[$shipment_item_id] = $shipment_item->getTitle();
    }

    foreach ($shipment->getOrder()->getItems() as $order_item) {
      // Skip shipment items that are already on this shipment.
      if (isset($shipment_item_options[$order_item->id()]) ||
        !$order_item->hasField('purchased_entity') ||
        in_array($order_item->id(), $already_on_shipment, TRUE)) {
        continue;
      }

      // Only allow items that aren't already on a shipment
      // have a purchasable entity and implement the shippable trait.
      $purchasable_entity = $order_item->getPurchasedEntity();
      if (!empty($purchasable_entity) && $purchasable_entity->hasField('weight')) {
        $shipment_item_options[$order_item->id()] = $order_item->label();
      }
    }

    return [
      '#type' => 'checkboxes',
      '#title' => $this->t('Shipment items'),
      '#options' => $shipment_item_options,
      '#default_value' => $shipment_item_defaults,
      '#required' => TRUE,
      '#weight' => 48,
    ];
  }

  /**
   * Builds the package type element.
   *
   * @return array
   *   The package type form element.
   */
  protected function buildPackageTypeElement() {
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $this->entity;

    $package_types = $this->packageTypeManager->getDefinitions();
    $package_type_options = [];
    foreach ($package_types as $package_type) {
      $unit = ' ' . array_pop($package_type['dimensions']);
      $dimensions = ' (' . implode(' x ', $package_type['dimensions']) . $unit . ')';
      $package_type_options[$package_type['id']] = $package_type['label'] . $dimensions;
    }

    $package_type = $shipment->getPackageType();
    return [
      '#type' => 'select',
      '#title' => $this->t('Package Type'),
      '#options' => $package_type_options,
      '#default_value' => $package_type ? $package_type->getId() : '',
      '#access' => count($package_types) > 1,
    ];

  }

  /**
   * Gets the shipment items that belong to another shipment
   * on the order.
   *
   * @return array
   *   The shipment items array
   */
  protected function getOrderShipmentItemsOnOtherShipments() {
    $already_on_shipment = [];

    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $this->entity;
    $order_shipments = $shipment->getOrder()->get('shipments')->referencedEntities();
    foreach ($order_shipments as $order_shipment) {
      if ($order_shipment->id() != $shipment->id()) {
        $shipment_items = $order_shipment->getItems();
        foreach ($shipment_items as $shipment_item) {
          $order_item_id = $shipment_item->getOrderItemId();
          $already_on_shipment[$order_item_id] = $order_item_id;
        }
      }
    }

    return $already_on_shipment;
  }

  /**
   * Creates new shipping items from the form and adds them to the shipment.
   *
   * @param array $form
   *   A nested array of form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function addShippingItems(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $this->entity;
    // Clear the shipping items to make sure the list is fresh when we add them.
    $shipment->setItems([]);
    /** @var \Drupal\commerce_shipping\ShipmentItem $shipment_item */
    foreach ($form_state->getValue('shipment_items') as $key => $value) {
      if ($value == 0) {
        // The item was not included in the shipment.
        continue;
      }
      /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
      $order_item = $this->entityTypeManager->getStorage('commerce_order_item')->load($key);
      $quantity = $order_item->getQuantity();
      $purchased_entity = $order_item->getPurchasedEntity();

      if ($purchased_entity->get('weight')->isEmpty()) {
        $weight = new Weight(1, WeightUnit::GRAM);
      }
      else {
        /** @var \Drupal\physical\Plugin\Field\FieldType\MeasurementItem $weight_item */
        $weight_item = $purchased_entity->get('weight')->first();
        $weight = $weight_item->toMeasurement();
      }

      $shipment_item = new ShipmentItem([
        'order_item_id' => $order_item->id(),
        'title' => $purchased_entity->label(),
        'quantity' => $quantity,
        'weight' => $weight->multiply($quantity),
        'declared_value' => $order_item->getTotalPrice(),
      ]);
      $shipment->addItem($shipment_item);
    }

    $this->shipmentPackager->packageShipment($shipment);
  }

}
