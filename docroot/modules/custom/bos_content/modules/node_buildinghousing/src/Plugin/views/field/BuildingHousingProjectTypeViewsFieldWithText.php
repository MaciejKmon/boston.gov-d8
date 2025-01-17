<?php

namespace Drupal\node_buildinghousing\Plugin\views\field;

use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Entity\EntityInterface as EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Random;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * A handler to provide a field that is completely custom by the administrator.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("building_housing_project_type_views_field_with_text")
 */
class BuildingHousingProjectTypeViewsFieldWithText extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function usesGroupBy() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Do nothing -- to override the parent query.
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['hide_alter_empty'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * Get Main Project Type Name.
   *
   * @param \Drupal\Core\Entity\EntityInterface $projectEntity
   *   Project Entity.
   *
   * @return mixed|string|null
   *   Main Project Type name string
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getMainProjectTypeName(EntityInterface $projectEntity) {
    $mainType = NULL;

    $termStorage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

    if ($dispositionTypeId = $projectEntity->get('field_bh_disposition_type')->target_id) {
      $dispositionTypeParents = $termStorage->loadAllParents($dispositionTypeId);
      $mainType = !empty($dispositionTypeParents) ? array_pop($dispositionTypeParents) : NULL;
    }

    if ($projectTypeId = $projectEntity->get('field_bh_project_type')->target_id) {
      if (empty($mainType) || $mainType->getName() == 'Housing') {
        $mainType = 'Housing';
      }
    }

    if ($mainType) {
      return is_string($mainType) ? $mainType : $mainType->getName();
    }

    return $mainType;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $mainType = $this->getMainProjectTypeName($values->_entity);

    $publicStageId = $values->_entity->field_bh_public_stage->target_id ?? NULL;
    $publicStage = $publicStageId ? Term::load($publicStageId) : NULL;
    $publicStage = $publicStage ? $publicStage->getName() : NULL;

    if ($mainType) {

      switch ($mainType) {
        case "Housing":
          $iconType = 'maplist-housing';
          $pillColor = 'charles-blue';
          $pillText = t('Housing');
          break;

        case "Open Space":
          $iconType = 'maplist-open-space';
          $pillColor = 'green';
          $pillText = t('Open Space');
          break;

        case "Business":
          $iconType = 'maplist-business';
          $pillColor = 'gray-blue';
          $pillText = t('Business');
          break;

        case "Abutter Sale":
        case "For Sale":
          $iconType = 'maplist-sale';
          // $pillColor = 'medium-gray';
          $pillColor = 'dark-gray';
          $pillText = t('For Sale');
          break;

        case "Other":
        default:
          // $iconType = 'maplist-other';
          $iconType = NULL;
          $pillColor = 'dark-gray';
          $pillText = t('To Be Decided');
          break;
      }

      if ($publicStage == 'Not Active') {
        $iconType = NULL;
        $pillColor = 'medium-gray';
        $pillText = t('DND Owned Land');
      }

      $elements = [];

      $elements['projectType'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['bh-project-type-pill', $pillColor]
        ],
      ];

      if ($iconType) {
        $elements['projectType']['icon']['#markup'] = \Drupal::theme()->render("bh_icons", ['type' => $iconType]);
      }

      $elements['projectType']['text'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $pillText,
      ];

      return $elements;
    }
  }

}
