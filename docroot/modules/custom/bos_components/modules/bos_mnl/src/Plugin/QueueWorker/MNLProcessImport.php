<?php

namespace Drupal\bos_mnl\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\bos_mnl\Controller\MnlUtilities;

/**
 * Processes import of nodes from queue.
 *
 * @QueueWorker(
 *   id = "mnl_import",
 *   title = @Translation("MNL Import records / nodes."),
 * )
 */
class MNLProcessImport extends QueueWorkerBase {

  /**
   * Cache the queue object.
   *
   * @var \Drupal\Core\Queue\DatabaseQueue
   */
  private $queue;

  /**
   * Array of SAM objects from the database. (Note: 400k records)
   *
   * @var array
   */
  private $mnl_cache;

  /**
   * Keeps queue statistics.
   *
   * @var array
   */
  private $stats = [];

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {

    $this->queue = \Drupal::queue($plugin_id);
    $cleanup = \Drupal::queue('mnl_cleanup');

    // If the queue is not empty, then prepare to process the queue
    if ($this->queue->numberOfItems() > 0) {
      // Fetch and load the cache.
      $this->mnl_cache = _bos_mnl_create_sam_cache();

      // Initialize the satistics array.
      $query = \Drupal::database()->select("node", "n")
        ->condition("n.type", "neighborhood_lookup");
      $query->addExpression("count(n.nid)", "count");
      $result = $query->execute()->fetch();

      $settings = \Drupal::configFactory()->getEditable('bos_mnl.settings');
      if (!empty($settings->get('tmp_import'))) {
        $this->stats = json_decode($settings->get('tmp_import'));
      }
      else {
        $this->stats = [
          "queue" => $this->queue->numberOfItems(),
          "cache" => $this->mnl_cache ? count($this->mnl_cache) : 0,
          "pre-entities" => $result ? $result->count : 0,
          "post-entities" => 0,
          "processed" => 0,
          "updated" => 0,
          "inserted" => 0,
          "unchanged" => 0,
          "cleanup-start" => $cleanup->numberOfItems(),
          "cleanup" => 0,
          "duplicateSAM" => 0,
          "duplicateNID" => 0,
          "starttime" => strtotime("now"),
        ];
      }

      \Drupal::logger("bos_mnl")
        ->info("Queue: MNL Import queue worker initialized.");
    }

   parent::__construct($configuration, $plugin_id, $plugin_definition);

  }

  /**
   * {@inheritdoc}
   */
  public function __destruct() {

    $start = microtime(TRUE);

    if ($this->stats["processed"] == 0) {
      $output = "MNL Import queue worker terminates: no neighborhood_lookup entities were processed.";
      \Drupal::logger("bos_mnl")->info($output);
    }

    \Drupal::logger("bos_mnl")
      ->info("Queue: MNL Import queue worker terminates.");

    // Work out how many entities there now are (for reporting).
    $query = \Drupal::database()->select("node", "n")
      ->condition("n.type", "neighborhood_lookup");
    $query->addExpression("count(n.nid)", "count");
    $result = $query->execute()->fetch();
    $this->stats["post-entities"] = $result->count;

    // Log.
    $cache_count = empty($this->mnl_cache) ? 0 : count($this->mnl_cache);
    $output = "
          <b>Completed at: " . date("Y-m-d H:i:s", strtotime("now")) . " UTC</b><br>
          == Start Condition =====================<br>
          <b>Addresses in DB at start</b>:       " . number_format($this->stats["pre-entities"], 0) . "<br>
          <b>Unique SAM ID's at start</b>:       " . number_format($this->stats["cache"], 0) . "<br>
          <b>Queue length at start</b>:          " . number_format($this->stats["queue"], 0) . " queued records.<br>
          <b>Cleanup queue length at start</b>:  " . number_format($this->stats["cleanup-start"], 0) . " queued records.<br>
          == Queue Processing ====================<br>
          <b>New addresses created</b>:          " . number_format($this->stats["inserted"], 0) . "<br>
          <b>Updated addresses</b>:              " . number_format($this->stats["updated"], 0) . "<br>
          <b>Unchanged addresses</b>:            " . number_format($this->stats["unchanged"], 0) . "<br>
          <b>Duplicate SAM ID's skipped</b>:     " . number_format($this->stats["duplicateSAM"], 0) . "<br>
          == Result ==============================<br>
          <b>Addresses processed from queue</b>: " . number_format($this->stats["processed"], 0) . "<br>
          <b>Addresses in DB at end</b>:         " . number_format($this->stats["post-entities"], 0) . "<br>
          <b>Unique SAM ID's at end</b>:         " . number_format($cache_count, 0) . "<br>
          <b>Queue length at end</b>:            " . number_format($this->queue->numberOfItems(), 0) . " queued records.<br>
          == Runtime =============================<br>
          <b>Process duration:</b> " . gmdate("H:i:s", strtotime("now") - $this->stats["starttime"]) . "<br>
          <i>${output}</i>
      ";
    \Drupal::logger("bos_mnl")->info($output);

    $settings = \Drupal::configFactory()->getEditable('bos_mnl.settings');
    if ($this->queue->numberOfItems() != 0) {
      // If the queue is not fully processed, then save the temporary stats
      $settings->set('tmp_import', json_encode($this->stats))->save();
    }
    else {
      // Queue is completely processed, update the stats
      $settings->set('tmp_import', "[]")->save();
      $settings->set('last_mnl_import', $output)->save();

      // It should now be safe to run the MNL cleanup function.
      try {
//        MnlUtilities::MnlCleanUp("5 Days Ago");
      }
      catch (Exception $e) {
        \Drupal::logger("bos_mnl")->error("Failed to cleanup. {$e}");
      }
    }
//    $elapsed = microtime(TRUE) - $start;
//    printf(" [info] " . $this->stats["cleanup"] . " Stale & duplicate records detected/enqueued in " . number_format($elapsed, 2) . " sec");

  }

  /**
   * Update node.
   *
   * @param mixed $existing_record
   *   The neighborhood lookup node.
   * @param array $new_record
   *   The import data object.
   */
  private function updateNode($existing_record, array $new_record) {
    $data_sam_record = json_encode($new_record["data"]);
    $data_sam_hash = hash("md5", $data_sam_record);
    $cache_sam_hash = $existing_record->field_checksum_value ?: "";
    $data_sam_address = $new_record["full_address"];
    $cache_sam_address = $existing_record->field_sam_address_value;

    if ($data_sam_hash != $cache_sam_hash || $data_sam_address != $cache_sam_address) {

      if ($data_sam_hash != $cache_sam_hash) {
        // The SAM data has changed - update data and checksum.
        _bos_mnl_update_sam_data($existing_record->nid, $data_sam_record, $data_sam_hash);
      }

      if ($data_sam_address != $cache_sam_address) {
        // The SAM Address has changed, update the address
        _bos_mnl_update_sam_address($existing_record->nid, $data_sam_address);
      }

      // Something changed, so update the lastupdated record.
      _bos_mnl_set_updated_date($existing_record->nid);

      // Force a save to invalidate the Drupal cache for this node.
      _bos_mnl_invalidate_cache($existing_record->nid);

      $this->stats["updated"]++;

    }
    else {
      $this->stats["unchanged"]++;
    }

  }

  /**
   * Create new node.
   *
   * @param mixed $existing_record
   *   The current node field values from the cache.
   * @param array $new_record
   *   The imported data to insert.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function createNode(&$existing_record, array $new_record) {

    $json_data = json_encode($new_record['data']);
    $md5 = hash("md5", $json_data);

    $node = _bos_mnl_add_sam_node($new_record, $json_data, $md5);

    // Add this new record to the cache.
    $existing_record->field_sam_id_value = $new_record['sam_address_id'];
    $existing_record->field_sam_address_value = $new_record['full_address'];
    $existing_record->field_checksum_value = $md5;
    $existing_record->nid = $node->id();

    $this->stats["inserted"]++;

  }

  /**
   * Process each queue record.
   *
   * @param mixed $item
   *   The item stored in the queue.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function processItem($item) {

    $item = (array) $item;
    $cache = isset($this->mnl_cache[$item['sam_address_id']]) ? $this->mnl_cache[$item['sam_address_id']] : FALSE;

    if ($cache) {
      if (!empty($cache->processed)) {
        $this->stats["duplicateSAM"]++;
      }
      else {
        $this->updateNode($cache, $item);
        $cache->processed = 1;
      }
    }

    else {
      $cache = new \stdClass();
      $this->createNode($cache, $item);
      $this->mnl_cache[$item['sam_address_id']] = $cache;
      $cache->processed = 1;
    }

    $this->stats["processed"]++;

  }

}
