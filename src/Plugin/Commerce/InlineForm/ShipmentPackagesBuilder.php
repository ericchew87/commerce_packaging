<?php


namespace Drupal\commerce_packaging\Plugin\Commerce\InlineForm;


use Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormBase;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\commerce_shipping\ShipmentItem;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\physical\Weight;
use Drupal\physical\WeightUnit;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an inline form for managing a packager settings.
 *
 *
 * @CommerceInlineForm(
 *   id = "shipment_packages_builder",
 *   label = @Translation("Shipment Package Builder"),
 * )
 */
class ShipmentPackagesBuilder extends EntityInlineFormBase {

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The package type manager.
   *
   * @var \Drupal\commerce_shipping\PackageTypeManagerInterface
   */
  protected $packageTypeManager;

  /**
   * The shared tempstore factory.
   *
   * @var \Drupal\Core\TempStore\SharedTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * Constructs a new PackagerSettings object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_shipping\PackageTypeManagerInterface $package_type_manager
   *   The package type manager.
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory $temp_store_factory
   *   The shared temp store factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match, EntityTypeManagerInterface $entity_type_manager, PackageTypeManagerInterface $package_type_manager, SharedTempStoreFactory $temp_store_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
    $this->packageTypeManager = $package_type_manager;
    $this->tempStoreFactory = $temp_store_factory;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.commerce_package_type'),
      $container->get('user.shared_tempstore')
    );
  }

  public function buildInlineForm(array $inline_form, FormStateInterface $form_state) {
    $inline_form = parent::buildInlineForm($inline_form, $form_state);

    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $this->entity;

    $package_types = $this->packageTypeManager->getDefinitions();
    $package_types = array_map(function ($package_type) {
      return $package_type['label'];
    }, $package_types);

    $inline_form['new_package'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'container-inline',
          'add-package',
        ],
      ],
    ];

    $inline_form['new_package']['new_package_submit'] = [
      '#type' => 'submit',
      '#value' => t('Add Package'),
      '#submit' => [[$this, 'addPackageSubmit']],
      '#ajax' => [
        'callback' => [$this, 'ajaxRefresh'],
        'wrapper' => 'shipment-packager',
      ],
    ];

    $inline_form['new_package']['new_package_select'] = [
      '#type' => 'select',
      '#options' => $package_types,
    ];

    $inline_form += $this->buildShipmentPackages($shipment);
    $inline_form['#attached']['library'][] = 'commerce_packaging/shipment_packager';

    return $inline_form;
  }

  /**
   * AJAX refresh for rebuilding the ShipmentPackager form.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return mixed
   */
  public static function ajaxRefresh(array $form, FormStateInterface $form_state) {
    return $form['shipment_packages']['shipment_packager'];
  }

  public function submitInlineForm(array &$inline_form, FormStateInterface $form_state) {
    parent::submitInlineForm($inline_form, $form_state);
    $temp_store = $this->getTempstore();
    $shipment = $temp_store->get('shipment');
    $packages = $temp_store->get('packages');
    $this->setEntity($shipment);
    $this->entity->set('packages', $packages);
    $temp_store->delete('packages');
    $temp_store->delete('shipment');
  }

  public function addPackageSubmit(array $form, FormStateInterface $form_state) {
    $temp_store = $this->getTempstore();
    $packages = $temp_store->get('packages');
    $selected_package_type = $form_state->getValue(['new_package', 'new_package_select']);
    $package_type = $this->packageTypeManager->createInstance($selected_package_type);
    /** @var \Drupal\commerce_packaging\Entity\ShipmentPackageInterface $package */
    $package = $this->entityTypeManager->getStorage('commerce_shipment_package')->create([
      'type' => $this->getShipmentPackageType($this->entity),
      'items' => [],
      'title' => $package_type->getLabel(),
      'package_type' => $package_type->getId(),
      'weight' => new Weight('0', 'g'),
    ]);
    $packages[] = $package;
    $temp_store->set('packages', $packages);
    $form_state->setRebuild();
  }

  /**
   * Removes a package from the shipment in TempStore.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function removePackageSubmit(array $form, FormStateInterface $form_state) {
    $temp_store = $this->getTempstore();
    $packages = $temp_store->get('packages');
    unset($packages[$form_state->getTriggeringElement()['#package_delta']]);
    $temp_store->set('packages', $packages);
    $form_state->setRebuild();
  }

  public function buildShipmentPackages(ShipmentInterface $shipment) {
    $build = [];
    $shipment_id = $shipment->id() ?: 'new';
    $order = $this->getOrder();

    $temp_store = $this->getTempstore();
    $packages = $temp_store->get('packages');
    if (!$packages) {
      $packages = $shipment->get('packages')->referencedEntities();
      $temp_store->set('packages', $packages);
    }

    $build['shipment_packager'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => [
          'shipment-packager',
        ],
      ],
    ];

    $build['shipment_packager']['shipment_items'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'shipment-packager__area',
          'shipment-items',
        ],
        'data-layout-update-url' => '/shipment_packages/move/'.$order->id().'/'.$shipment_id,
      ],
    ];

    $build['shipment_packager']['shipment_items']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => t('Un-Packaged Items'),
      '#attributes' => [
        'class' => ['shipment-items-title'],
      ],
    ];

    $build['shipment_packager']['packages'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'package-area',
        ],
        'data-layout-update-url' => '/shipment_packages/move/'.$order->id().'/'.$shipment_id,
      ],
    ];

    $unpackaged_items = [];
    foreach ($order->getItems() as $order_item) {
      $unpackaged_items[$order_item->id()] = [
        'quantity' => (int)$order_item->getQuantity(),
        'order_item' => $order_item,
      ];
    }

    $order_shipments = $order->get('shipments')->referencedEntities();
    foreach ($order_shipments as $shipment_id => $order_shipment) {
      if ($order_shipment->id() != $shipment->id()) {
        $shipment_items = $order_shipment->getItems();
        /** @var \Drupal\commerce_shipping\ShipmentItem $shipment_item */
        foreach ($shipment_items as $shipment_item_delta => $shipment_item) {
          if (!empty($unpackaged_items[$shipment_item->getOrderItemId()])) {
            $unpackaged_items[$shipment_item->getOrderItemId()]['quantity'] -= $shipment_item->getQuantity();
          }
        }
      }
    }

    $shipment_items = [];
    foreach ($unpackaged_items as $unpackaged_item) {
      /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
      $order_item = $unpackaged_item['order_item'];
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
      $shipment_items[] = $shipment_item;
    }
    $shipment->setItems($shipment_items);

    foreach ($packages as $delta => $package) {
      $build['shipment_packager']['packages'][$delta] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['package'],
        ],
      ];

      $build['shipment_packager']['packages'][$delta]['package_header'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['package-header'],
        ],
      ];

      $build['shipment_packager']['packages'][$delta]['package_header']['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $package->getTitle(),
        '#attributes' => [
          'class' => ['package-title'],
        ],
      ];

      $build['shipment_packager']['packages'][$delta]['package_header']['remove-' . $delta] = [
        '#type' => 'submit',
        '#value' => t('Remove Package ' . $delta),
        '#package_delta' => $delta,
        '#submit' => [[$this, 'removePackageSubmit']],
        '#attributes' => [
          'class' => [
            'remove-package',
          ],
        ],
        '#ajax' => [
          'callback' => [$this, 'ajaxRefresh'],
          'wrapper' => 'shipment-packager',
          'progress' => [
            'type' => 'fullscreen',
          ],
        ],
      ];

      $build['shipment_packager']['packages'][$delta]['items'] = [
        '#type' => 'container',
        '#attributes' => [
          'data-package-id' => $delta,
          'class' => ['shipment-packager__area'],
        ],
      ];

      foreach ($package->getItems() as $item) {
        $id = $item->getOrderItemId();
        $quantity = (int)$item->getQuantity();
        $unpackaged_items[$id]['quantity'] -= $quantity;
        $build['shipment_packager']['packages'][$delta]['items'][] = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => t($item->getTitle() . ' x' . $quantity),
          '#attributes' => [
            'data-shipment-item-id' => $id . '-' . $quantity,
            'class' => ['draggable', 'shipment-item'],
          ],
        ];
      }
    }

    foreach ($unpackaged_items as $order_item_id => $data) {
      $quantity = $data['quantity'];
      $title = $data['order_item']->getTitle();

      if ($data['quantity'] > 0) {
        $build['shipment_packager']['shipment_items'][] = [
          '#type' => 'container',
          '#markup' => $title . ' x' . $quantity,
          '#attributes' => [
            'data-shipment-item-id' => $order_item_id . '-' . $quantity,
            'class' => [
              'draggable',
              'shipment-item',
            ],
          ],
        ];
      }
    }

    $temp_store->set('shipment', $shipment);

    return $build;
  }

  /**
   * Gets the Order from shipment or route match if not set.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   */
  protected function getOrder() {
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $this->entity;
    $order_id = $shipment->get('order_id')->target_id;

    if (!$order_id) {
      $order_id = $this->routeMatch->getParameter('commerce_order');
      $shipment->set('order_id', $order_id);
    }

    return $shipment->getOrder();
  }

  /**
   * Returns the TempStore.
   *
   * @return \Drupal\Core\TempStore\SharedTempStore
   *   The tempstore.
   */
  protected function getTempstore() {
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $this->entity;
    $shipment_id = $shipment->id() ?: 'new';
    $collection = 'commerce_packaging.order.' . $shipment->getOrderId() . '.shipment.' . $shipment_id;

    return $this->tempStoreFactory->get($collection);
  }

  /**
   * Gets the shipment package type for a shipment.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   *
   * @return string
   *   The shipment package type.
   */
  protected function getShipmentPackageType(ShipmentInterface $shipment) {
    $shipment_type_storage = $this->entityTypeManager->getStorage('commerce_shipment_type');
    /** @var \Drupal\commerce_shipping\Entity\ShipmentTypeInterface $shipment_type */
    $shipment_type = $shipment_type_storage->load($shipment->bundle());

    return $shipment_type->getThirdPartySetting('commerce_packaging', 'shipment_package_type');
  }

}
