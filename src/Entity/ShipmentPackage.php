<?php

namespace Drupal\commerce_packaging\Entity;

use Drupal\commerce_packaging\ProposedShipmentPackage;
use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Plugin\Commerce\PackageType\PackageTypeInterface as PackageTypePluginInterface;
use Drupal\commerce_shipping\ShipmentItem;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\physical\Weight;

/**
 * Defines the shipment package entity class.
 *
 * @ContentEntityType(
 *   id = "commerce_shipment_package",
 *   label = @Translation("Shipment Package"),
 *   label_collection = @Translation("Shipment packages"),
 *   label_singular = @Translation("shipment package"),
 *   label_plural = @Translation("shipment packages"),
 *   label_count = @PluralTranslation(
 *     singular = "@count shipment package",
 *     plural = "@count shipment packages",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\commerce_packaging\ShipmentPackageListBuilder",
 *     "storage" = "Drupal\commerce\CommerceContentEntityStorage",
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "edit" = "Drupal\Core\Entity\ContentEntityForm",
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "commerce_shipment_package",
 *   admin_permission = "administer commerce_shipment_package",
 *   fieldable = TRUE,
 *   entity_keys = {
 *     "id" = "package_id",
 *     "bundle" = "type",
 *     "label" = "title",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "canonical" = "/admin/commerce/orders/{commerce_order}/shipments/{commerce_shipment}/packages/{commerce_shipment_package}",
 *     "collection" = "/admin/commerce/orders/{commerce_order}/shipments/{commerce_shipment}/packages",
 *     "edit-form" = "/admin/commerce/orders/{commerce_order}/shipments/{commerce_shipment}/packages/{commerce_shipment_package}/edit",
 *   },
 *   bundle_entity_type = "commerce_shipment_package_type",
 *   field_ui_base_route = "entity.commerce_shipment_package_type.edit_form",
 * )
 */

class ShipmentPackage extends ContentEntityBase implements ShipmentPackageInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);
    if ($shipment = $this->getShipment()) {
      $uri_route_parameters['commerce_order'] = $shipment->getOrderId();
      $uri_route_parameters['commerce_shipment'] = $shipment->id();
    }

    return $uri_route_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function populateFromProposedShipmentPackage(ProposedShipmentPackage $proposed_shipment_package) {
    if ($proposed_shipment_package->getType() != $this->bundle()) {
      throw new \InvalidArgumentException(sprintf('The proposed shipment package type "%s" does not match the shipment package type "%s".', $proposed_shipment_package->getType(), $this->bundle()));
    }

    $this->set('shipment_id', $proposed_shipment_package->getShipmentId());
    $this->set('title', $proposed_shipment_package->getTitle());
    $this->set('items', $proposed_shipment_package->getItems());
    $this->set('package_type', $proposed_shipment_package->getPackageType()->getId());
    foreach ($proposed_shipment_package->getCustomFields() as $field_name => $value) {
      if ($this->hasField($field_name)) {
        $this->set($field_name, $value);
      }
      else {
        $this->setData($field_name, $value);
      }
    }
    $this->recalculateWeight();
    $this->recalculateDeclaredValue();
  }

  /**
   * {@inheritdoc}
   */
  public function getShipment() {
    return $this->get('shipment_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getShipmentId() {
    return $this->get('shipment_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->get('title')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle($title) {
    $this->set('title', $title);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getItems() {
    return $this->get('items')->getShipmentItems();
  }

  /**
   * {@inheritdoc}
   */
  public function setItems(array $shipment_items) {
    $this->set('items', $shipment_items);
    $this->recalculateWeight();
    $this->recalculateDeclaredValue();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasItems() {
    return !$this->get('items')->isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public function addItem(ShipmentItem $shipment_item) {
    $this->get('items')->appendItem($shipment_item);
    $this->recalculateWeight();
    $this->recalculateDeclaredValue();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeItem(ShipmentItem $shipment_item) {
    $items = $this->get('items');
    foreach ($items as $key => $item) {
      if ($item->value == $shipment_item) {
        $items->removeItem($key);
        $this->recalculateWeight();
        $this->recalculateDeclaredValue();
        break;
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPackageType() {
    if (!$this->get('package_type')->isEmpty()) {
      $package_type_id = $this->get('package_type')->value;
      $package_type_manager = \Drupal::service('plugin.manager.commerce_package_type');
      return $package_type_manager->createInstance($package_type_id);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setPackageType(PackageTypePluginInterface $package_type) {
    $this->set('package_type', $package_type->getId());
    $this->recalculateWeight();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTrackingCode() {
    return $this->get('tracking_code')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTrackingCode($tracking_code) {
    $this->set('tracking_code', $tracking_code);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDeclaredValue() {
    if (!$this->get('declared_value')->isEmpty()) {
      return $this->get('declared_value')->first()->toPrice();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setDeclaredValue(Price $declared_value) {
    $this->set('declared_value', $declared_value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function recalculateDeclaredValue() {
    $total_declared_value = NULL;
    foreach ($this->getItems() as $item) {
      $declared_value = $item->getDeclaredValue();
      $total_declared_value = $total_declared_value ? $total_declared_value->add($declared_value) : $declared_value;
    }
    $this->setDeclaredValue($total_declared_value);
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    if (!$this->get('weight')->isEmpty()) {
      return $this->get('weight')->first()->toMeasurement();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight(Weight $weight) {
    $this->set('weight', $weight);
    return $this;
  }

  /**
   * Recalculates the package's weight.
   */
  protected function recalculateWeight() {
    if (!$this->hasItems()) {
      // Can't calculate the weight if the items are still unavailable.
      return;
    }

    /** @var \Drupal\physical\Weight $weight */
    $weight = NULL;
    foreach ($this->getItems() as $shipment_item) {
      $shipment_item_weight = $shipment_item->getWeight();
      $weight = $weight ? $weight->add($shipment_item_weight) : $shipment_item_weight;
    }
    if ($package_type = $this->getPackageType()) {
      $package_type_weight = $package_type->getWeight();
      $weight = $weight->add($package_type_weight);
    }

    $this->setWeight($weight);
  }

  /**
   * {@inheritdoc}
   */
  public function getData($key, $default = NULL) {
    $data = [];
    if (!$this->get('data')->isEmpty()) {
      $data = $this->get('data')->first()->getValue();
    }
    return isset($data[$key]) ? $data[$key] : $default;
  }

  /**
   * {@inheritdoc}
   */
  public function setData($key, $value) {
    $this->get('data')->__set($key, $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // @TODO do this in a hook?
    // The shipment backreference, populated by Shipment::postSave().
    $fields['shipment_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Shipment'))
      ->setDescription(t('The parent shipment.'))
      ->setSetting('target_type', 'commerce_shipment')
      ->setReadOnly(TRUE);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('The package title.'))
      ->setRequired(TRUE)
      ->setSettings([
        'default_value' => '',
        'max_length' => 255,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['items'] = BaseFieldDefinition::create('commerce_shipment_item')
      ->setLabel(t('Items'))
      ->setRequired(TRUE)
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['package_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Package type'))
      ->setDescription(t('The package type.'))
      ->setRequired(TRUE)
      ->setDefaultValue('')
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('view', TRUE);

    $fields['weight'] = BaseFieldDefinition::create('physical_measurement')
      ->setLabel(t('Weight'))
      ->setRequired(TRUE)
      ->setSetting('measurement_type', 'weight')
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['declared_value'] = BaseFieldDefinition::create('commerce_price')
      ->setLabel(t('Declared Value'))
      ->setDescription(t('The package declared value.'))
      ->setReadOnly(TRUE)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['tracking_code'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Tracking code'))
      ->setDescription(t('The package tracking code.'))
      ->setDefaultValue('')
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['data'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Data'))
      ->setDescription(t('A serialized array of additional data.'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time when the package was created.'))
      ->setRequired(TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time when the package was last updated.'))
      ->setRequired(TRUE);

    return $fields;
  }

}
