<?php


namespace Drupal\commerce_packaging;


use Drupal\commerce_shipping\ShipmentItem;

class ProposedShipmentPackage {

  /**
   * The shipment package type.
   *
   * @var string
   */
  protected $type;

  /**
   * The shipmented package ID.
   *
   * @var int
   */
  protected $shipmentId;

  /**
   * The shipment package title.
   *
   * @var string
   */
  protected $title;

  /**
   * The shipment items.
   *
   * @var \Drupal\commerce_shipping\ShipmentItem[]
   */
  protected $items = [];

  /**
   * The package type plugin.
   *
   * @var \Drupal\commerce_shipping\Plugin\Commerce\PackageType\PackageTypeInterface
   */
  protected $packageType;

  /**
   * The weight.
   *
   * @var \Drupal\physical\Weight
   */
  protected $weight;

  /**
   * The declared value.
   *
   * @var \Drupal\commerce_price\Price
   */
  protected $declaredValue;

  /**
   * The custom fields.
   *
   * @var array
   */
  protected $customFields = [];

  /**
   * Constructs a new ProposedShipment object.
   *
   * @param array $definition
   *   The definition.
   */
  public function __construct(array $definition) {
    foreach (['type', 'title', 'items', 'package_type', 'weight', 'declared_value'] as $required_property) {
      if (empty($definition[$required_property])) {
        throw new \InvalidArgumentException(sprintf('Missing required property "%s".', $required_property));
      }
    }
    foreach ($definition['items'] as $shipment_item) {
      if (!($shipment_item instanceof ShipmentItem)) {
        throw new \InvalidArgumentException('Each shipment item under the "items" property must be an instance of ShipmentItem.');
      }
    }

    $this->type = $definition['type'];
    $this->shipmentId = $definition['shipment_id'];
    $this->title = $definition['title'];
    $this->items = $definition['items'];
    $this->packageType = $definition['package_type'];
    $this->weight = $definition['weight'];
    $this->declaredValue = $definition['declared_value'];
    if (!empty($definition['custom_fields'])) {
      $this->customFields = $definition['custom_fields'];
    }
  }

  public function getType() {
    return $this->type;
  }

  public function getShipmentId() {
    return $this->shipmentId;
  }

  public function getTitle() {
    return $this->title;
  }

  public function getItems() {
    return $this->items;
  }

  public function getPackageType() {
    return $this->packageType;
  }

  public function getWeight() {
    return $this->weight;
  }

  public function getDeclaredValue() {
    return $this->declaredValue;
  }

  public function getCustomFields() {
    return $this->customFields;
  }

}
