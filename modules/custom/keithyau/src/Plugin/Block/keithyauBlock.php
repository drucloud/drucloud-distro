<?php
 
namespace Drupal\keithyau\Plugin\Block;
 
use Drupal\block\BlockBase;
use Drupal\Core\Session\AccountInterface;
 
/**
 * Provides a 'Demo' block.
 *
 * @Block(
 *   id = "demo_block",
 *   admin_label = @Translation("Demo block"),
 * )
 */
 
class keithyauBlock extends BlockBase {
   
  /**
   * {@inheritdoc}
   */
  public function build() {    
    $config = $this->getConfiguration();
   
    if (isset($config['demo_block_settings']) && !empty($config['demo_block_settings'])) {
      $name = $config['demo_block_settings'];
    }
    else {
      $name = $this->t('to no one');
    }
   
    return array(
      '#markup' => $this->t('Hello @name!', array('@name' => $name)),
    );  
  }
   
  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    return $account->hasPermission('access content');
  }  
   
  /**
   * {@inheritdoc}
   */
  public function blockForm($form, &$form_state) {
   
    $form = parent::blockForm($form, $form_state);
   
    $config = $this->getConfiguration();
 
    $form['demo_block_settings'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Who'),
      '#description' => $this->t('Who do you want to say hello to?'),
      '#default_value' => isset($config['demo_block_settings']) ? $config['demo_block_settings'] : '',
    );
   
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, &$form_state) {
  
   $this->setConfigurationValue('demo_block_settings', $form_state['values']['demo_block_settings']);
  
  } 
}
