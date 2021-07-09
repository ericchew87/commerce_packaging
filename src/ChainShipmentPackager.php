<?php


namespace Drupal\commerce_packaging;


use Drupal\commerce_packaging\Resolver\ChainPackagingStrategyResolverInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Entity\ShippingMethodInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

class ChainShipmentPackager implements ChainShipmentPackagerInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The chain packaging strategy resolver.
   *
   * @var \Drupal\commerce_packaging\Resolver\ChainPackagingStrategyResolverInterface
   */
  protected $packagingStrategyResolver;

  /**
   * ChainChainShipmentPackager constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\commerce_packaging\Resolver\ChainPackagingStrategyResolverInterface $packaging_strategy_resolver
   *   The chain packaging strategy resolver.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger, ChainPackagingStrategyResolverInterface $packaging_strategy_resolver) {
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->packagingStrategyResolver = $packaging_strategy_resolver;
  }

  /**
   * {@inheritDoc}
   */
  public function getPackagingStrategy(ShippingMethodInterface $shipping_method, ShipmentInterface $shipment) {
    return $this->packagingStrategyResolver->resolve($shipping_method, $shipment);
  }

  /**
   * {@inheritDoc}
   */
  public function packageShipment(ShippingMethodInterface $shipping_method, ShipmentInterface $shipment) {
    // Ensure the packagers know what type of shipment packages to create.
    $shipment_type = $this->entityTypeManager->getStorage('commerce_shipment_type')->load($shipment->bundle());
    if (!$shipment_type->getThirdPartySetting('commerce_packaging', 'shipment_package_type')) {
      $this->messenger->addWarning($this->t('Packagers could not run because because the %type shipment type does not specify a shipment package type', ['%type' => $shipment_type->label()]));
      return $shipment;
    }

    $packaging_strategy = $this->getPackagingStrategy($shipping_method, $shipment);
    if ($packaging_strategy) {
      $shipment_packagers = $packaging_strategy->getShipmentPackagers();
      if (!empty($shipment_packagers)) {
        $shipment->setPackageType($packaging_strategy->getDefaultPackageType());

        $proposed_shipment_packages = [];
        $unpackaged_items = $shipment->getItems();

        foreach ($shipment_packagers as $shipment_packager) {
          list($proposed_shipment_packages, $unpackaged_items) = $shipment_packager->packageItems($shipment, $unpackaged_items);
          if (empty($unpackaged_items)) {
            break;
          }
        }
        if (!empty($unpackaged_items)) {
          $this->messenger->addMessage($this->t('Not all of the shipment items were packaged!'));
        }

        $shipment_package_data = $shipment->getData('proposed_shipment_packages', []);
        $shipment_package_data[$shipping_method->id()] = $proposed_shipment_packages;
        $shipment->setData('proposed_shipment_packages', $shipment_package_data);
      }
    }

    return $shipment;
  }

  /**
   * {@inheritDoc}
   */
  public function finalizePackages(ShipmentInterface $shipment) {
    $proposed_shipment_packages = $shipment->getData('proposed_shipment_packages', []);
    if (empty($proposed_shipment_packages[$shipment->getShippingMethodId()])) {
      return $shipment;
    }

    $shipment_package_storage = $this->entityTypeManager->getStorage('commerce_shipment_package');
    $current_packages = $shipment->get('packages')->referencedEntities();
    $shipment_package_storage->delete($current_packages);

    $shipment_packages = [];
    /** @var \Drupal\commerce_packaging\ProposedShipmentPackage $proposed_shipment_package */
    foreach ($proposed_shipment_packages[$shipment->getShippingMethodId()] as $proposed_shipment_package) {
      /** @var \Drupal\commerce_packaging\Entity\ShipmentPackageInterface $shipment_package */
      $shipment_package = $shipment_package_storage->create([
        'type' => $proposed_shipment_package->getType(),
        'shipment_id' => [$shipment]
      ]);
      $shipment_package->populateFromProposedShipmentPackage($proposed_shipment_package);
      $shipment_packages[] = $shipment_package;
    }
    $shipment->set('packages', $shipment_packages);
    $shipment->setData('proposed_shipment_packages', NULL);

    return $shipment;
  }

}
