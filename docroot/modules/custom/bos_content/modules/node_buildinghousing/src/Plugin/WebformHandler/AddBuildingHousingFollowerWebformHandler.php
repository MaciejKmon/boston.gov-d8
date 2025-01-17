<?php

namespace Drupal\node_buildinghousing\Plugin\WebformHandler;

use Drupal\salesforce\Exception;
use Drupal\salesforce\SelectQuery;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Create a new node entity from a webform submission.
 *
 * @WebformHandler(
 *   id = "Add a Building Housing Follower",
 *   label = @Translation("Add a Building Housing Follower"),
 *   category = "Building Housing",
 *   description = @Translation("Add a Building Housing Follower on SF via a Webform."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
 * )
 */
class AddBuildingHousingFollowerWebformHandler extends WebformHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {

    $fieldData = $webform_submission->getData();

    if ($webform_submission->isCompleted()) {
      $project = $webform_submission->getSourceEntity() ?? NULL;
      $webUpdate = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->loadByProperties([
          'type' => 'bh_update',
          'field_sf_web_update' => TRUE,
          'field_bh_project_ref' => $project->id()
        ]);
      $webUpdate = count($webUpdate) ? reset($webUpdate) : NULL;

      if ($webUpdate) {
        $currentFollowers = $webUpdate->get('field_bh_follower_emails')->value;
        $newEmail = $fieldData['email_address'] . '; ';

        if ($currentFollowers) {
          $webUpdate->set('field_bh_follower_emails', $currentFollowers . $newEmail);
        }
        else {
          $webUpdate->set('field_bh_follower_emails', $newEmail);
        }

        $webUpdate->save();
      }

    }

    // TODO: Change the autogenerated stub.
    parent::postSave($webform_submission, $update);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(WebformSubmissionInterface $webform_submission) {

    // TODO: Change the autogenerated stub.
    parent::preSave($webform_submission);
  }

}
