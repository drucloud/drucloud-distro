<?php

/**
 * @file
 * Contains \Drupal\search_api\Form\IndexForm.
 */

namespace Drupal\search_api\Form;

use Drupal\Component\Utility\String;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Datasource\DatasourcePluginManager;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Tracker\TrackerPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for the Index entity.
 */
class IndexForm extends EntityForm {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The datasource plugin manager.
   *
   * @var \Drupal\search_api\Datasource\DatasourcePluginManager
   */
  protected $datasourcePluginManager;

  /**
   * The tracker plugin manager.
   *
   * @var \Drupal\search_api\Tracker\TrackerPluginManager
   */
  protected $trackerPluginManager;

  /**
   * Constructs an IndexForm object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\search_api\Datasource\DatasourcePluginManager $datasource_plugin_manager
   *   The search datasource plugin manager.
   * @param \Drupal\search_api\Tracker\TrackerPluginManager $tracker_plugin_manager
   *   The Search API tracker plugin manager.
   */
  public function __construct(EntityManagerInterface $entity_manager, DatasourcePluginManager $datasource_plugin_manager, TrackerPluginManager $tracker_plugin_manager) {
    $this->entityManager = $entity_manager;
    $this->datasourcePluginManager = $datasource_plugin_manager;
    $this->trackerPluginManager = $tracker_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\Core\Entity\EntityManagerInterface $entity_manager */
    $entity_manager = $container->get('entity.manager');
    /** @var \Drupal\search_api\Datasource\DatasourcePluginManager $datasource_plugin_manager */
    $datasource_plugin_manager = $container->get('plugin.manager.search_api.datasource');
    /** @var \Drupal\search_api\Tracker\TrackerPluginManager $tracker_plugin_manager */
    $tracker_plugin_manager = $container->get('plugin.manager.search_api.tracker');
    return new static($entity_manager, $datasource_plugin_manager, $tracker_plugin_manager);
  }

  /**
   * Returns the entity manager.
   *
   * @return \Drupal\Core\Entity\EntityManagerInterface
   *   The entity manager.
   */
  protected function getEntityManager() {
    return $this->entityManager ?: \Drupal::service('entity.manager');
  }

  /**
   * Returns the datasource plugin manager.
   *
   * @return \Drupal\search_api\Datasource\DatasourcePluginManager
   *   The datasource plugin manager.
   */
  protected function getDatasourcePluginManager() {
    return $this->datasourcePluginManager ?: \Drupal::service('plugin.manager.search_api.datasource');
  }

  /**
   * Returns the tracker plugin manager.
   *
   * @return \Drupal\search_api\Tracker\TrackerPluginManager
   *   The tracker plugin manager.
   */
  protected function getTrackerPluginManager() {
    return $this->trackerPluginManager ?: \Drupal::service('plugin.manager.search_api.tracker');
  }

  /**
   * Returns the index storage controller.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   The index storage controller.
   */
  protected function getIndexStorage() {
    return $this->getEntityManager()->getStorage('search_api_index');
  }

  /**
   * Returns the server storage controller.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   The server storage controller.
   */
  protected function getServerStorage() {
    return $this->getEntityManager()->getStorage('search_api_server');
  }

  /**
   * Retrieves all available servers as an options list.
   *
   * @return string[]
   *   An associative array mapping server IDs to their labels.
   */
  protected function getServerOptions() {
    $options = array();
    /** @var \Drupal\search_api\Server\ServerInterface $server */
    foreach ($this->getServerStorage()->loadMultiple() as $server_id => $server) {
      // @todo Special formatting for disabled servers.
      $options[$server_id] = String::checkPlain($server->label());
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    // If the form is being rebuilt, rebuild the entity with the current form
    // values.
    if ($form_state->isRebuilding()) {
      $this->entity = $this->buildEntity($form, $form_state);
    }

    $form = parent::form($form, $form_state);

    /** @var \Drupal\search_api\Index\IndexInterface $index */
    $index = $this->getEntity();
    if ($index->isNew()) {
      $form['#title'] = $this->t('Add search index');
    }
    else {
      $form['#title'] = $this->t('Edit search index %label', array('%label' => $index->label()));
    }

    $this->buildEntityForm($form, $form_state, $index);

    return $form;
  }

  /**
   * Builds the form for the basic index properties.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   The index that is being created or edited.
   */
  public function buildEntityForm(array &$form, FormStateInterface $form_state, IndexInterface $index) {
    $form['#tree'] = TRUE;
    $form['name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Index name'),
      '#description' => $this->t('Enter the displayed name for the index.'),
      '#default_value' => $index->label(),
      '#required' => TRUE,
    );
    $form['machine_name'] = array(
      '#type' => 'machine_name',
      '#default_value' => $index->id(),
      '#maxlength' => 50,
      '#required' => TRUE,
      '#machine_name' => array(
        'exists' => array($this->getIndexStorage(), 'load'),
        'source' => array('name'),
      ),
    );

    // If the user changed the datasources or the tracker, notify them that they
    // need to be configured.
    // @todo Only do that if the datasources/tracker have configuration forms.
    //   (Same in ServerForm.)
    $values = $form_state->getValues();
    if (!empty($values['datasources'])) {
      drupal_set_message($this->t('Please configure the used datasources.'), 'warning');
    }

    if (!empty($values['tracker'])) {
      drupal_set_message($this->t('Please configure the used tracker.'), 'warning');
    }

    $form['#attached']['library'][] = 'search_api/drupal.search_api.admin_css';

    $datasource_options = array();
    foreach ($this->getDatasourcePluginManager()->getDefinitions() as $datasource_id => $definition) {
      $datasource_options[$datasource_id] = !empty($definition['label']) ? $definition['label'] : $datasource_id;
    }
    $form['datasources'] = array(
      '#type' => 'select',
      '#title' => $this->t('Data types'),
      '#description' => $this->t('Select one or more data type of items that will be stored in this index.'),
      '#options' => $datasource_options,
      '#default_value' => $index->getDatasourceIds(),
      '#multiple' => TRUE,
      '#required' => TRUE,
      '#ajax' => array(
        'trigger_as' => array('name' => 'datasourcepluginids_configure'),
        'callback' => array(get_class($this), 'buildAjaxDatasourceConfigForm'),
        'wrapper' => 'search-api-datasources-config-form',
        'method' => 'replace',
        'effect' => 'fade',
      ),
    );

    $form['datasource_configs'] = array(
      '#type' => 'container',
      '#attributes' => array(
        'id' => 'search-api-datasources-config-form',
      ),
      '#tree' => TRUE,
    );

    $form['datasource_configure_button'] = array(
      '#type' => 'submit',
      '#name' => 'datasourcepluginids_configure',
      '#value' => $this->t('Configure'),
      '#limit_validation_errors' => array(array('datasources')),
      '#submit' => array(array(get_class($this), 'submitAjaxDatasourceConfigForm')),
      '#ajax' => array(
        'callback' => array(get_class($this), 'buildAjaxDatasourceConfigForm'),
        'wrapper' => 'search-api-datasources-config-form',
      ),
      '#attributes' => array('class' => array('js-hide')),
    );

    $this->buildDatasourcesConfigForm($form, $form_state, $index);

    $tracker_options = $this->getTrackerPluginManager()->getDefinitionLabels();
    $form['tracker'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Tracker'),
      '#description' => $this->t('Select the type of tracker which should be used for keeping track of item changes.'),
      '#options' => $this->getTrackerPluginManager()->getDefinitionLabels(),
      '#default_value' => $index->hasValidTracker() ? $index->getTracker()->getPluginId() : key($tracker_options),
      '#required' => TRUE,
      '#disabled' => !$index->isNew(),
      '#ajax' => array(
        'trigger_as' => array('name' => 'trackerpluginid_configure'),
        'callback' => array(get_class($this), 'buildAjaxTrackerConfigForm'),
        'wrapper' => 'search-api-tracker-config-form',
        'method' => 'replace',
        'effect' => 'fade',
      ),
      '#access' => count($tracker_options) > 1,
    );

    $form['tracker_config'] = array(
      '#type' => 'container',
      '#attributes' => array(
        'id' => 'search-api-tracker-config-form',
      ),
      '#tree' => TRUE,
    );

    $form['tracker_configure_button'] = array(
      '#type' => 'submit',
      '#name' => 'trackerpluginid_configure',
      '#value' => $this->t('Configure'),
      '#limit_validation_errors' => array(array('tracker')),
      '#submit' => array(array(get_class($this), 'submitAjaxTrackerConfigForm')),
      '#ajax' => array(
        'callback' => array(get_class($this), 'buildAjaxTrackerConfigForm'),
        'wrapper' => 'search-api-tracker-config-form',
      ),
      '#attributes' => array('class' => array('js-hide')),
      '#access' => count($tracker_options) > 1,
    );

    $this->buildTrackerConfigForm($form, $form_state, $index);

    $form['server'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Server'),
      '#description' => $this->t('Select the server this index should use. Indexes cannot be enabled without a connection to a valid, enabled server.'),
      '#options' => array(NULL => '<em>' . $this->t('- No server -') . '</em>') + $this->getServerOptions(),
      '#default_value' => $index->hasValidServer() ? $index->getServerId() : NULL,
    );

    $form['status'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#description' => $this->t('Only enabled indexes can be used for indexing and searching. This setting will only take effect if the selected server is also enabled.'),
      '#default_value' => $index->status(),
      // Can't enable an index lying on a disabled server or no server at all.
      '#disabled' => !$index->status() && (!$index->hasValidServer() || !$index->getServer()->status()),
      // @todo This doesn't seem to work and should also hide for disabled
      //   servers. If that works, we can probably remove the last sentence of
      //   the description.
      '#states' => array(
        'invisible' => array(
          ':input[name="server"]' => array('value' => '')
        ),
      ),
    );

    $form['description'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#description' => $this->t('Enter a description for the index.'),
      '#default_value' => $index->getDescription(),
    );

    $form['options'] = array(
      '#tree' => TRUE,
      '#type' => 'details',
      '#title' => $this->t('Index options'),
      '#collapsed' => TRUE,
    );

    // We display the "read-only" flag along with the other options, even though
    // it is a property directly on the index object. We use "#parents" to move
    // it to the correct place in the form values.
    $form['options']['read_only'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Read only'),
      '#description' => $this->t('Do not write to this index or track the status of items in this index.'),
      '#default_value' => $index->isReadOnly(),
      '#parents' => array('read_only'),
    );
    $form['options']['index_directly'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Index items immediately'),
      '#description' => $this->t('Immediately index new or updated items instead of waiting for the next cron run. This might have serious performance drawbacks and is generally not advised for larger sites.'),
      '#default_value' => $index->getOption('index_directly'),
    );
    $form['options']['cron_limit'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Cron batch size'),
      '#description' => $this->t('Set how many items will be indexed at once when indexing items during a cron run. "0" means that no items will be indexed by cron for this index, "-1" means that cron should index all items at once.'),
      '#default_value' => $index->getOption('cron_limit'),
      '#size' => 4,
    );
  }


  /**
   * Builds the configuration forms for all selected datasources.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   The index begin created or edited.
   */
  public function buildDatasourcesConfigForm(array &$form, FormStateInterface $form_state, IndexInterface $index) {
    foreach ($index->getDatasources() as $datasource_id => $datasource) {
      if ($datasource_plugin_config_form = $datasource->buildConfigurationForm(array(), $form_state)) {
        $form['datasource_configs'][$datasource_id]['#type'] = 'details';
        $form['datasource_configs'][$datasource_id]['#title'] = $this->t('Configure the %datasource datasource', array('%datasource' => $datasource->getPluginDefinition()['label']));
        $form['datasource_configs'][$datasource_id]['#open'] = $index->isNew();

        $form['datasource_configs'][$datasource_id] += $datasource_plugin_config_form;
      }
    }
  }

  /**
   * Builds the tracker configuration form.
   *
   * @param \Drupal\search_api\Index\IndexInterface index
   *   The index begin created or edited.
   */
  public function buildTrackerConfigForm(array &$form, FormStateInterface $form_state, IndexInterface $index) {
    if ($index->hasValidTracker()) {
      $tracker = $index->getTracker();
      $tracker_plugin_definition = $tracker->getPluginDefinition();

      if ($tracker_plugin_config_form = $tracker->buildConfigurationForm(array(), $form_state)) {
        $form['tracker_config']['#type'] = 'details';
        $form['tracker_config']['#title'] = $this->t('Configure %plugin', array('%plugin' => $tracker_plugin_definition['label']));
        $form['tracker_config']['#description'] = String::checkPlain($tracker_plugin_definition['description']);
        $form['tracker_config']['#open'] = $index->isNew();

        $form['tracker_config'] += $tracker_plugin_config_form;
      }
    }
    // Only notify the user of a missing tracker plugin if we're editing an
    // existing index.
    elseif (!$index->isNew()) {
      drupal_set_message($this->t('The tracker plugin is missing or invalid.'), 'error');
    }
  }

  /**
   * Form submission handler for buildEntityForm().
   *
   * Takes care of changes in the selected datasources.
   */
  public static function submitAjaxDatasourceConfigForm($form, FormStateInterface $form_state) {
    $form_state->setRebuild();
  }

  /**
   * Handles changes to the selected datasources.
   */
  public static function buildAjaxDatasourceConfigForm(array $form, FormStateInterface $form_state) {
    return $form['datasource_configs'];
  }

  /**
   * Form submission handler for buildEntityForm().
   *
   * Takes care of changes in the selected tracker plugin.
   */
  public static function submitAjaxTrackerConfigForm($form, FormStateInterface $form_state) {
    $form_state->setRebuild();
  }

  /**
   * Handles switching the selected tracker plugin.
   */
  public static function buildAjaxTrackerConfigForm(array $form, FormStateInterface $form_state) {
    return $form['tracker_config'];
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array $form, FormStateInterface $form_state) {
    parent::validate($form, $form_state);

    /** @var $index \Drupal\search_api\Index\IndexInterface */
    $index = $this->getEntity();

    // Store the array of datasource plugin IDs with integer keys.
    $values = $form_state->getValues();
    $datasource_ids = array_values($values['datasources']);
    $form_state->setValue('datasources', $datasource_ids);

    // Call validateConfigurationForm() for each enabled datasource.
    // @todo Do we want to also call validate and submit callbacks for plugins
    //   without configuration forms? We currently don't for backend plugins,
    //   but do it here. We should be consistent.
    /** @var \Drupal\search_api\Datasource\DatasourceInterface[] $datasource_plugins */
    $datasource_plugins = array();
    $datasource_forms = array();
    $datasource_form_states = array();
    foreach ($datasource_ids as $datasource_id) {
      if ($index->isValidDatasource($datasource_id)) {
        $datasource_plugins[$datasource_id] = $index->getDatasource($datasource_id);
      }
      else {
        $datasource_plugins[$datasource_id] = $this->datasourcePluginManager->createInstance($datasource_id, array('index' => $index));
      }
      $datasource_forms[$datasource_id] = array();
      if (!empty($form['datasource_configs'][$datasource_id])) {
        $datasource_forms[$datasource_id] = &$form['datasource_configs'][$datasource_id];
      }
      $datasource_form_states[$datasource_id] = new SubFormState($form_state, array('datasource_configs', $datasource_id));
      $datasource_plugins[$datasource_id]->validateConfigurationForm($datasource_forms[$datasource_id], $datasource_form_states[$datasource_id]);
    }
    $form_state->set('datasource_plugins', $datasource_plugins);
    $form_state->set('datasource_forms', $datasource_forms);
    $form_state->set('datasource_form_states', $datasource_form_states);

    // Call validateConfigurationForm() for the (possibly new) tracker.
    // @todo It seems if we change the tracker, we would validate/submit the old
    //   tracker's form using the new tracker. Shouldn't be done, of course.
    //   Similar above for datasources, though there of course the values will
    //   just always be empty (because datasources have their plugin ID in the
    //   form structure).
    $tracker_id = $values['tracker'];
    if ($index->getTrackerId() == $tracker_id) {
      $tracker = $index->getTracker();
    }
    else {
      $tracker = $this->trackerPluginManager->createInstance($tracker_id, array('index' => $index));
    }
    $tracker_form_state = new SubFormState($form_state, array('tracker_config'));
    $tracker->validateConfigurationForm($form['tracker_config'], $tracker_form_state);
    $form_state->set('tracker_plugin', $tracker);
    $form_state->set('tracker_form_state', $tracker_form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    /** @var $index \Drupal\search_api\Index\IndexInterface */
    $index = $this->getEntity();

    $form_state->setValue('options', array_merge($index->getOptions(), $form_state->getValues()['options']));

    $datasource_forms = $form_state->get('datasource_forms');
    $datasource_form_states = $form_state->get('datasource_form_states');
    /** @var \Drupal\search_api\Datasource\DatasourceInterface $datasource */
    foreach ($form_state->get('datasource_plugins') as $datasource_id => $datasource) {
      $datasource->submitConfigurationForm($datasource_forms[$datasource_id], $datasource_form_states[$datasource_id]);
    }

    /** @var \Drupal\search_api\Tracker\TrackerInterface $tracker */
    $tracker = $form_state->get('tracker_plugin');
    $tracker_form_state = $form_state->get('tracker_form_state');
    $tracker->submitConfigurationForm($form['tracker_config'], $tracker_form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // @todo Redirect to a confirm form if changing server or tracker, since
    //   that isn't such a light operation (equaling a "clear", basically).

    // Only save the index if the form doesn't need to be rebuilt.
    if (!$form_state->isRebuilding()) {
      try {
        $this->getEntity()->save();
        drupal_set_message($this->t('The index was successfully saved.'));
        $form_state->setRedirect('entity.search_api_index.canonical', array('search_api_index' => $this->getEntity()->id()));
      }
      catch (\Exception $ex) {
        $form_state->setRebuild();
        watchdog_exception('search_api', $ex);
        drupal_set_message($this->t('The index could not be saved.'), 'error');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete(array $form, FormStateInterface $form_state) {
    $form_state->setRedirect('entity.search_api_index.delete', array('search_api_index' => $this->getEntity()->id()));
  }

}
