<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\SearchApiServerTaskUnitTest.
 */

namespace Drupal\search_api\Tests;

use Drupal\search_api\Exception\SearchApiException;
use Drupal\simpletest\KernelTestBase;

/**
 * Tests whether the server task system works correctly.
 *
 * @group search_api
 */
class SearchApiServerTaskUnitTest extends KernelTestBase {

  /**
   * The test server.
   *
   * @var \Drupal\search_api\Server\ServerInterface
   */
  protected $server;

  /**
   * The test index.
   *
   * @var \Drupal\search_api\Index\IndexInterface
   */
  protected $index;

  /**
   * The content entity datasource.
   *
   * @var \Drupal\search_api\Datasource\DatasourceInterface
   */
  protected $datasource;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('entity', 'user', 'search_api', 'search_api_test_backend');

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The server task manager to use for the tests.
   *
   * @var \Drupal\search_api\Task\ServerTaskManagerInterface
   */
  protected $serverTaskManager;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->state = $this->container->get('state');

    $this->installEntitySchema('user');
    $this->installSchema('search_api', array('search_api_item', 'search_api_task'));

    // Create a test server.
    $this->server = entity_create('search_api_server', array(
      'name' => $this->randomString(),
      'machine_name' => $this->randomMachineName(),
      'status' => 1,
      'backend' => 'search_api_test_backend',
    ));
    $this->server->save();

    // Create a test index.
    $this->index = entity_create('search_api_index', array(
      'name' => $this->randomString(),
      'machine_name' => $this->randomMachineName(),
      'status' => 1,
      'datasources' => array('entity:user'),
      'tracker' => 'default_tracker',
      'server' => $this->server->id(),
      'options' => array('index_directly' => FALSE),
    ));
    $this->index->save();

    $this->serverTaskManager = $this->container->get('entity.search_api_server.task_manager');
  }

  /**
   * Tests task system integration for the server's addIndex() method.
   */
  public function testAddIndex() {
    // Since we want to add the index, we should first remove it (even though it
    // shouldn't matter – just for logic consistency).
    $this->index->setServer(NULL);
    $this->index->save();

    // Set exception for addIndex() and reset the list of successful backend
    // method calls.
    $this->state->set('search_api_test_backend.exception.addIndex', TRUE);
    $this->getCalledServerMethods();

    // Try to add the index.
    $this->server->addIndex($this->index);
    $this->assertEqual($this->getCalledServerMethods(), array(), 'addIndex correctly threw an exception.');
    $tasks = $this->getServerTasks();
    if (count($tasks) == 1) {
      $task_created = $tasks[0]->type === 'addIndex';
    }
    $this->assertTrue(!empty($task_created), 'The addIndex task was successfully added.');
    if ($tasks) {
      $this->assertEqual($tasks[0]->index_id, $this->index->id(), 'The right index ID was used for the addIndex task.');
    }

    // Check whether other task-system-integrated methods now fail, too.
    $this->server->updateIndex($this->index);
    $this->assertEqual($this->getCalledServerMethods(), array(), 'updateIndex was not executed.');
    $tasks = $this->getServerTasks();
    if (count($tasks) == 2) {
      $this->pass("Second task ('updateIndex') was added.");
      $this->assertEqual($tasks[0]->type, 'addIndex', 'First task stayed the same.');
      $this->assertEqual($tasks[1]->type, 'updateIndex', 'New task was queued as last.');
    }
    else {
      $this->fail("Second task (updateIndex) was not added.");
    }

    // Let addIndex() succeed again, then trigger the task execution with a cron
    // run.
    $this->state->set('search_api_test_backend.exception.addIndex', FALSE);
    search_api_cron();
    $this->assertEqual($this->getServerTasks(), array(), 'Server tasks were correctly executed.');
    $this->assertEqual($this->getCalledServerMethods(), array('addIndex', 'updateIndex'), 'Right methods were called during task execution.');
  }

  /**
   * Tests task system integration for the server's updateIndex() method.
   */
  public function testUpdateIndex() {
    // Set exception for updateIndex() and reset the list of successful backend
    // method calls.
    $this->state->set('search_api_test_backend.exception.updateIndex', TRUE);
    $this->getCalledServerMethods();

    // Try to update the index.
    $this->server->updateIndex($this->index);
    $this->assertEqual($this->getCalledServerMethods(), array(), 'updateIndex correctly threw an exception.');
    $tasks = $this->getServerTasks();
    if (count($tasks) == 1) {
      $task_created = $tasks[0]->type === 'updateIndex';
    }
    $this->assertTrue(!empty($task_created), 'The updateIndex task was successfully added.');
    if ($tasks) {
      $this->assertEqual($tasks[0]->index_id, $this->index->id(), 'The right index ID was used for the updateIndex task.');
    }

    // Check whether other task-system-integrated methods now fail, too.
    $this->server->deleteAllIndexItems($this->index);
    $this->assertEqual($this->getCalledServerMethods(), array(), 'deleteAllIndexItems was not executed.');
    $tasks = $this->getServerTasks();
    if (count($tasks) == 2) {
      $this->pass("Second task ('deleteAllIndexItems') was added.");
      $this->assertEqual($tasks[0]->type, 'updateIndex', 'First task stayed the same.');
      $this->assertEqual($tasks[1]->type, 'deleteAllIndexItems', 'New task was queued as last.');
    }
    else {
      $this->fail("Second task (deleteAllIndexItems) was not added.");
    }

    // Let updateIndex() succeed again, then trigger the task execution with a
    // call to indexItems().
    $this->state->set('search_api_test_backend.exception.updateIndex', FALSE);
    $this->server->indexItems($this->index, array());
    $this->assertEqual($this->getServerTasks(), array(), 'Server tasks were correctly executed.');
    $this->assertEqual($this->getCalledServerMethods(), array('updateIndex', 'deleteAllIndexItems', 'indexItems'), 'Right methods were called during task execution.');
  }

  /**
   * Tests task system integration for the server's removeIndex() method.
   */
  public function testRemoveIndex() {
    // Set exception for updateIndex() and removeIndex(), and reset the list of
    // successful backend method calls.
    $this->state->set('search_api_test_backend.exception.updateIndex', TRUE);
    $this->state->set('search_api_test_backend.exception.removeIndex', TRUE);
    $this->getCalledServerMethods();

    // First try to update the index and fail. Then try to remove it and check
    // that the tasks were set correctly.
    $this->server->updateIndex($this->index);
    $this->server->removeIndex($this->index);
    $this->assertEqual($this->getCalledServerMethods(), array(), 'updateIndex and removeIndex correctly threw exceptions.');
    $tasks = $this->getServerTasks();
    if (count($tasks) == 1) {
      $task_created = $tasks[0]->type === 'removeIndex';
    }
    $this->assertTrue(!empty($task_created), 'The removeIndex task was successfully added and other tasks removed.');
    if ($tasks) {
      $this->assertEqual($tasks[0]->index_id, $this->index->id(), 'The right index ID was used for the removeIndex task.');
    }

    // Check whether other task-system-integrated methods now fail, too.
    try {
      $this->server->indexItems($this->index, array());
      $this->fail('Pending server tasks did not prevent indexing of items.');
    }
    catch (SearchApiException $e) {
      $expected_message = 'Could not index items because pending server tasks could not be executed.';
      $this->assertEqual($e->getMessage(), $expected_message, 'Pending server tasks prevented indexing of items.');
    }
    $this->assertEqual($this->getCalledServerMethods(), array(), 'indexItems was not executed.');
    $tasks = $this->getServerTasks();
    $this->assertEqual(count($tasks), 1, 'No task added for indexItems.');

    // Let removeIndex() succeed again, then trigger the task execution with a
    // cron run.
    $this->state->set("search_api_test_backend.exception.removeIndex", FALSE);
    search_api_cron();
    $this->assertEqual($this->getServerTasks(), array(), 'Server tasks were correctly executed.');
    $this->assertEqual($this->getCalledServerMethods(), array('removeIndex'), 'Right methods were called during task execution.');
  }

  /**
   * Tests task system integration for the server's deleteItems() method.
   */
  public function testDeleteItems() {
    // Set exception for deleteItems() and reset the list of successful
    // backend method calls.
    $this->state->set('search_api_test_backend.exception.deleteItems', TRUE);
    $this->getCalledServerMethods();

    // Try to update the index.
    $this->server->deleteItems($this->index, array());
    $this->assertEqual($this->getCalledServerMethods(), array(), 'deleteItems correctly threw an exception.');
    $tasks = $this->getServerTasks();
    if (count($tasks) == 1) {
      $task_created = $tasks[0]->type === 'deleteItems';
    }
    $this->assertTrue(!empty($task_created), 'The deleteItems task was successfully added.');
    if ($tasks) {
      $this->assertEqual($tasks[0]->index_id, $this->index->id(), 'The right index ID was used for the deleteItems task.');
    }

    // Check whether other task-system-integrated methods now fail, too.
    $this->server->updateIndex($this->index);
    $this->assertEqual($this->getCalledServerMethods(), array(), 'updateIndex was not executed.');
    $tasks = $this->getServerTasks();
    if (count($tasks) == 2) {
      $this->pass("Second task ('updateIndex') was added.");
      $this->assertEqual($tasks[0]->type, 'deleteItems', 'First task stayed the same.');
      $this->assertEqual($tasks[1]->type, 'updateIndex', 'New task was queued as last.');
    }
    else {
      $this->fail("Second task (updateIndex) was not added.");
    }

    // Let deleteItems() succeed again, then trigger the task execution
    // with a cron run.
    $this->state->set('search_api_test_backend.exception.deleteItems', FALSE);
    search_api_cron();
    $this->assertEqual($this->getServerTasks(), array(), 'Server tasks were correctly executed.');
    $this->assertEqual($this->getCalledServerMethods(), array('deleteItems', 'updateIndex'), 'Right methods were called during task execution.');
  }

  /**
   * Tests task system integration for the deleteAllIndexItems() method.
   */
  public function testDeleteAllIndexItems() {
    // Set exception for deleteAllIndexItems() and reset the list of successful
    // backend method calls.
    $this->state->set('search_api_test_backend.exception.deleteAllIndexItems', TRUE);
    $this->getCalledServerMethods();

    // Try to update the index.
    $this->server->deleteAllIndexItems($this->index);
    $this->assertEqual($this->getCalledServerMethods(), array(), 'deleteAllIndexItems correctly threw an exception.');
    $tasks = $this->getServerTasks();
    if (count($tasks) == 1) {
      $task_created = $tasks[0]->type === 'deleteAllIndexItems';
    }
    $this->assertTrue(!empty($task_created), 'The deleteAllIndexItems task was successfully added.');
    if ($tasks) {
      $this->assertEqual($tasks[0]->index_id, $this->index->id(), 'The right index ID was used for the deleteAllIndexItems task.');
    }

    // Check whether other task-system-integrated methods now fail, too.
    $this->server->updateIndex($this->index);
    $this->assertEqual($this->getCalledServerMethods(), array(), 'updateIndex was not executed.');
    $tasks = $this->getServerTasks();
    if (count($tasks) == 2) {
      $this->pass("Second task ('updateIndex') was added.");
      $this->assertEqual($tasks[0]->type, 'deleteAllIndexItems', 'First task stayed the same.');
      $this->assertEqual($tasks[1]->type, 'updateIndex', 'New task was queued as last.');
    }
    else {
      $this->fail("Second task (updateIndex) was not added.");
    }

    // Let deleteAllIndexItems() succeed again, then trigger the task execution
    // with a call to indexItems().
    $this->state->set('search_api_test_backend.exception.deleteAllIndexItems', FALSE);
    $this->server->indexItems($this->index, array());
    $this->assertEqual($this->getServerTasks(), array(), 'Server tasks were correctly executed.');
    $this->assertEqual($this->getCalledServerMethods(), array('deleteAllIndexItems', 'updateIndex', 'indexItems'), 'Right methods were called during task execution.');
  }

  /**
   * Retrieves the methods called on the test server.
   *
   * @param bool $reset
   *   (optional) Whether to reset the list after the called methods are
   *   retrieved.
   *
   * @return string[]
   *   The methods called on the test server since the last reset.
   */
  protected function getCalledServerMethods($reset = TRUE) {
    $key = 'search_api_test_backend.methods_called.' . $this->server->id();
    $methods_called = $this->state->get($key, array());
    if ($reset) {
      $this->state->delete($key);
    }
    return $methods_called;
  }

  /**
   * Get the tasks set on the test server.
   *
   * @return object[]
   *   All tasks read from the database for the test server, with numeric keys
   *   starting with 0.
   */
  protected function getServerTasks() {
    $tasks = array();
    $select = \Drupal::database()->select('search_api_task', 't');
    $select->fields('t')
      ->orderBy('id')
      ->condition('server_id', $this->server->id());
    foreach ($select->execute() as $task) {
      if ($task->data) {
        $task->data = unserialize($task->data);
      }
      $tasks[] = $task;
    }
    return $tasks;
  }

}
