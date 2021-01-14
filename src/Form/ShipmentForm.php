<?php


namespace Drupal\commerce_packaging\Form;


use Drupal\commerce_packaging\ChainShipmentPackagerInterface;
use Drupal\commerce_shipping\Form\ShipmentForm as ShipmentFormBase;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ShipmentForm extends ShipmentFormBase {

  /**
   * The shipment packager.
   *
   * @var \Drupal\commerce_packaging\ChainShipmentPackagerInterface
   */
  protected $shipmentPackager;

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
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, PackageTypeManagerInterface $package_type_manager, ChainShipmentPackagerInterface $shipment_packager) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time, $package_type_manager);
    $this->shipmentPackager = $shipment_packager;
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
      $container->get('commerce_packaging.chain_shipment_packager')
    );
  }

  public function form(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $this->entity;

    $this->ensureOrder();
    $this->ensureShippingProfile();

    // Store the original amount for ShipmentForm::save().
    $form_state->set('original_amount', $shipment->getAmount());

    $this->getFormDisplay($form_state)->buildForm($shipment, $form, $form_state);

    // The ShippingProfileWidget doesn't output a fieldset because that makes
    // sense in a checkout context, but on the admin form it is clearer for
    // profile fields to be visually grouped.
    $form['shipping_profile']['widget'][0]['#type'] = 'fieldset';

    // Fixes illegal choice has been detected message upon AJAX reload.
    if (empty($form['shipping_method']['widget'][0]['#options'])) {
      $form['shipping_method']['#access'] = FALSE;
    }

    // Prepare the form for ajax.
    // Not using Html::getUniqueId() on the wrapper ID to avoid #2675688.
    $form['#wrapper_id'] = 'shipping-information-wrapper';
    $form['#prefix'] = '<div id="' . $form['#wrapper_id'] . '">';
    $form['#suffix'] = '</div>';

    $form['package_type'] = $this->buildPackageTypeElement();
    $form['shipment_items'] = $this->buildShipmentItemsElement();

    $form['recalculate_shipping'] = [
      '#type' => 'button',
      '#value' => $this->t('Recalculate shipping'),
      '#recalculate' => TRUE,
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxRefresh'],
        'wrapper' => $form['#wrapper_id'],
      ],
      // The calculation process only needs a valid shipping profile.
      '#limit_validation_errors' => [
        array_merge($form['#parents'], ['shipping_profile']),
      ],
      '#weight' => 49,
      '#after_build' => [
        [static::class, 'clearValues'],
      ],
    ];

    return $form;
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
   * Ensures the shipment has a shipping profile.
   */
  protected function ensureShippingProfile() {
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $this->entity;
    $shipping_profile = $shipment->getShippingProfile();
    if (!$shipping_profile) {
      /** @var \Drupal\commerce_shipping\Entity\ShipmentTypeInterface $shipment_type */
      $shipment_type = $this->entityTypeManager->getStorage('commerce_shipment_type')->load($shipment->bundle());
      /** @var \Drupal\profile\Entity\ProfileInterface $shipping_profile */
      $shipping_profile = $this->entityTypeManager->getStorage('profile')->create([
        'type' => $shipment_type->getProfileTypeId(),
        'uid' => 0,
      ]);
      $address = [
        '#type' => 'address',
        '#default_value' => [],
      ];
      $shipping_profile->set('address', $address);
      $shipment->setShippingProfile($shipping_profile);
    }
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
   * {@inheritDoc}
   */
  protected function addShippingItems(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $this->entity;

    $triggering_element = $form_state->getTriggeringElement();
    // If the shipment has packages then the ShipmentItems were already updated by the packagers.
    if (empty($triggering_element['#recalculate']) && $shipment->hasField('packages') && !$shipment->get('packages')->isEmpty()) {
      return;
    }

    parent::addShippingItems($form, $form_state);
    $this->shipmentPackager->packageShipment($shipment);
  }

}
