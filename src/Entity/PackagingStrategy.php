<?php

namespace Drupal\commerce_packaging\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Packaging strategy entity.
 *
 * @ConfigEntityType(
 *   id = "commerce_packaging_strategy",
 *   label = @Translation("Packaging strategy"),
 *   label_collection = @Translation("Packaging strategies"),
 *   label_singular = @Translation("packaging strategy"),
 *   label_plural = @Translation("packaging stratagies"),
 *   label_count = @PluralTranslation(
 *     singular = "@count packaging strategy",
 *     plural = "@count packaging strategies",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\commerce_packaging\PackagingStrategyListBuilder",
 *     "form" = {
 *       "add" = "Drupal\commerce_packaging\Form\PackagingStrategyForm",
 *       "edit" = "Drupal\commerce_packaging\Form\PackagingStrategyForm",
 *       "delete" = "Drupal\commerce_packaging\Form\PackagingStrategyDeleteForm"
 *     },
 *     "local_task_provider" = {
 *       "default" = "Drupal\entity\Menu\DefaultEntityLocalTaskProvider",
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "commerce_packaging_strategy",
 *   admin_permission = "administer commerce_packaging_strategy",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "default_package_type",
 *     "packagers",
 *   },
 *   links = {
 *     "add-form" = "/admin/commerce/config/packaging-strategy/add",
 *     "edit-form" = "/admin/commerce/config/packaging-strategy/{commerce_packaging_strategy}/edit",
 *     "delete-form" = "/admin/commerce/config/packaging-strategy/{commerce_packaging_strategy}/delete",
 *     "collection" = "/admin/commerce/config/packaging-strategy"
 *   }
 * )
 */
class PackagingStrategy extends ConfigEntityBase implements PackagingStrategyInterface {

  /**
   * The Packaging strategy ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Packaging strategy label.
   *
   * @var string
   */
  protected $label;


  /**
   * The default package type.
   *
   * @var string
   */
  protected $default_package_type;

  /**
   * The packagers.
   *
   * @var array
   */
  protected $packagers = [];

}
