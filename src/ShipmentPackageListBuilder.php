<?php

namespace Drupal\commerce_packaging;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the list builder for packages.
 */
class ShipmentPackageListBuilder extends EntityListBuilder {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The currency formatter.
   *
   * @var \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface
   */
  protected $currencyFormatter;

  /**
   * {@inheritdoc}
   */
  protected $entitiesKey = 'shipments';

  /**
   * Constructs a new PackageListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface $currency_formatter
   *   The number formatter factory.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, CurrencyFormatterInterface $currency_formatter, RouteMatchInterface $route_match) {
    parent::__construct($entity_type, $storage);

    $this->currencyFormatter = $currency_formatter;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('commerce_price.currency_formatter'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_shipment_packages';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    $shipment_id = $this->routeMatch->getParameter('commerce_shipment');
    $query = $this->getStorage()->getQuery()
      ->condition('shipment_id', $shipment_id)
      ->sort($this->entityType->getKey('id'));

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }
    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'label' => $this->t('Package'),
      'tracking' => $this->t('Tracking'),
      'declared_value' => $this->t('Declared Value'),
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\commerce_packaging\Entity\ShipmentPackageInterface $entity */
    $amount = $entity->getDeclaredValue();

    $row['label'] = $entity->getTitle();
    $row['tracking'] = $entity->getTrackingCode();
    $row['declared_value'] = $this->currencyFormatter->format($amount->getNumber(), $amount->getCurrencyCode());

    return $row + parent::buildRow($entity);
  }

}
