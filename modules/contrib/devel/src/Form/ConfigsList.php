<?php

/**
 * @file
 * Contains \Drupal\devel\Form\ConfigsList.
 */

namespace Drupal\devel\Form;

use Drupal\Component\Utility\String;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form that displays all the config variables to edit them.
 */
class ConfigsList extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'devel_config_system_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $filter = '') {
    $form['filter'] = array(
    '#type' => 'details',
    '#title' => t('Filter variables'),
    '#attributes' => array('class' => array('container-inline')),
    );
    $form['filter']['name'] = array(
      '#type' => 'textfield',
      '#title' => t('Variable name'),
      '#title_display' => 'invisible',
      '#default_value' => $filter,
    );
    $form['filter']['show'] = array(
      '#type' => 'submit',
      '#value' => t('Filter'),
    );
    $header = array(
      'name' => array('data' => t('Name')),
      'edit' => array('data' => t('Operations')),
    );
    $form['variables'] = array(
      '#type' => 'table',
      '#header' => $header,
    );

    // List all the variables filtered if any filter was provided.
    $names = \Drupal::configFactory()->listAll($filter);
    foreach ($names as $key => $config_name) {
      $form['variables'][$key]['name'] = array('#markup' => $config_name);
      $url = new Url('devel.config_edit', array('config_name' => $config_name));
      $form['variables'][$key]['operation'] = array('#markup' => \Drupal::l(t('Edit'), $url));
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirectUrl(Url::FromRoute('devel.config_edit', array('config_name' => String::checkPlain($form_state->getvalue('name')))));
  }

}
