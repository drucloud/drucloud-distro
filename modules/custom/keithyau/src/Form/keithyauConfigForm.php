<?php
 
/**
 * @file
 * Contains \Drupal\keithyau\Form\DemoForm.
 */
 
namespace Drupal\keithyau\Form;
 
use Drupal\Core\Form\ConfigFormBase;
 
class keithyauForm extends FormBase {
   
  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'keithyau_form';
  }
   
  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, array &$form_state) {
     
    $form = parent::buildForm($form, $form_state);
   
    $config = $this->config('keithyau.settings');
   
    $form['email'] = array(
      '#type' => 'email',
      '#title' => $this->t('Your .com email address.'),
      '#default_value' => $config->get('keithyau.email_address')
    );
 
    return $form;
  }
   
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, array &$form_state) {
     
    if (strpos($form_state['values']['email'], '.com') === FALSE ) {
      $this->setFormError('email', $form_state, $this->t('This is not a .com email address.'));
    } 
  }
   
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
   
    $config = $this->config('keithyau.settings');
    $config->set('keithyau.email_address', $form_state['values']['email']);
    $config->save();
   
    return parent::submitForm($form, $form_state);
  }
   
}
