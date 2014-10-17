<?php

/**
 * @file
 * Contains \Drupal\devel_generate\Plugin\DevelGenerate\MenuDevelGenerate.
 */

namespace Drupal\devel_generate\Plugin\DevelGenerate;

use Drupal\devel_generate\DevelGenerateBase;
use Drupal\system\Entity\Menu;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a MenuDevelGenerate plugin.
 *
 * @DevelGenerate(
 *   id = "menu",
 *   label = @Translation("menus"),
 *   description = @Translation("Generate a given number of menus and menu links. Optionally delete current menus."),
 *   url = "menu",
 *   permission = "administer devel_generate",
 *   settings = {
 *     "num_menus" = 2,
 *     "num_links" = 50,
 *     "title_length" = 12,
 *     "max_width" = 6,
 *     "kill" = FALSE,
 *   }
 * )
 */
class MenuDevelGenerate extends DevelGenerateBase implements ContainerFactoryPluginInterface {

  /**
   * The menu link plugin manager.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $menuLinkManager;

  /**
   * The menu tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuLinkTree;

  /**
   * Constructs a MenuDevelGenerate object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Menu\MenuLinkManagerInterface $menu_link_manager
   *   The menu link plugin manager.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_tree
   *   The menu tree service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MenuLinkManagerInterface $menu_link_manager, MenuLinkTreeInterface $menu_tree) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->menuLinkManager = $menu_link_manager;
    $this->menuLinkTree = $menu_tree;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('plugin.manager.menu.link'),
      $container->get('menu.link_tree')
    );
  }

  public function settingsForm(array $form, FormStateInterface $form_state) {
    $menu_enabled = \Drupal::moduleHandler()->moduleExists('menu_ui');
    if ($menu_enabled) {
      $menus = array('__new-menu__' => t('Create new menu(s)')) + menu_ui_get_menus();
    }
    else {
      $menus = menu_list_system_menus();
    }
    $form['existing_menus'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Generate links for these menus'),
      '#options' => $menus,
      '#default_value' => array('__new-menu__'),
      '#required' => TRUE,
    );
    if ($menu_enabled) {
      $form['num_menus'] = array(
        '#type' => 'textfield',
        '#title' => t('Number of new menus to create'),
        '#default_value' => $this->getSetting('num_menus'),
        '#size' => 10,
        '#states' => array(
          'visible' => array(
            ':input[name=existing_menus[__new-menu__]]' => array('checked' => TRUE),
          ),
        ),
      );
    }
    $form['num_links'] = array(
      '#type' => 'textfield',
      '#title' => t('Number of links to generate'),
      '#default_value' => $this->getSetting('num_links'),
      '#size' => 10,
      '#required' => TRUE,
    );
    $form['title_length'] = array(
      '#type' => 'textfield',
      '#title' => t('Maximum number of characters in menu and menu link names'),
      '#description' => t("The minimum length is 2."),
      '#default_value' => $this->getSetting('title_length'),
      '#size' => 10,
      '#required' => TRUE,
    );
    $form['link_types'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Types of links to generate'),
      '#options' => array(
        'node' => t('Nodes'),
        'front' => t('Front page'),
        'external' => t('External'),
      ),
      '#default_value' => array('node', 'front', 'external'),
      '#required' => TRUE,
    );
    $form['max_depth'] = array(
      '#type' => 'select',
      '#title' => t('Maximum link depth'),
      '#options' => range(0, $this->menuLinkTree->maxDepth()),
      '#default_value' => floor($this->menuLinkTree->maxDepth() / 2),
      '#required' => TRUE,
    );
    unset($form['max_depth']['#options'][0]);
    $form['max_width'] = array(
      '#type' => 'textfield',
      '#title' => t('Maximum menu width'),
      '#default_value' => $this->getSetting('max_width'),
      '#size' => 10,
      '#description' => t("Limit the width of the generated menu's first level of links to a certain number of items."),
      '#required' => TRUE,
    );
    $form['kill'] = array(
      '#type' => 'checkbox',
      '#title' => t('Delete existing custom generated menus and menu links before generating new ones.'),
      '#default_value' => $this->getSetting('kill'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function generateElements(array $values) {
    // If the create new menus checkbox is off, set the number of new menus to 0.
    if (!isset($values['existing_menus']['__new-menu__']) || !$values['existing_menus']['__new-menu__']) {
      $values['num_menus'] = 0;
    }
    else {
      // Unset the aux menu to avoid attach menu new items.
      unset($values['existing_menus']['__new-menu__']);
    }

    // Delete custom menus.
    if ($values['kill']) {
      $this->deleteMenus();
      $this->setMessage(t('Deleted existing menus and links.'));
    }

    // Generate new menus.
    $new_menus = $this->generateMenus($values['num_menus'], $values['title_length']);
    if (!empty($new_menus)) {
      $this->setMessage(t('Created the following new menus: !menus', array('!menus' => implode(', ', $new_menus))));
    }

    // Generate new menu links.
    $menus = $new_menus;
    if (isset($values['existing_menus'])) {
      $menus = $menus + $values['existing_menus'];
    }
    $new_links = $this->generateLinks($values['num_links'], $menus, $values['title_length'], $values['link_types'], $values['max_depth'], $values['max_width']);
    $this->setMessage(t('Created @count new menu links.', array('@count' => count($new_links))));
  }

  public function validateDrushParams($args) {

    $link_types = array('node', 'front', 'external');
    $values = array(
      'num_menus' => array_shift($args),
      'num_links' => array_shift($args),
      'kill' => drush_get_option('kill'),
      'pipe' => drush_get_option('pipe'),
      'link_types' => array_combine($link_types, $link_types),
    );

    $max_depth = array_shift($args);
    $max_width = array_shift($args);
    $values['max_depth'] =  $max_depth ? $max_depth : 3;
    $values['max_width'] =  $max_width ? $max_width : 8;
    $values['title_length'] = $this->getSetting('title_length');
    $values['existing_menus']['__new-menu__'] = TRUE;

    if ($this->isNumber($values['num_menus']) == FALSE) {
      return drush_set_error('DEVEL_GENERATE_INVALID_INPUT', dt('Invalid number of menus'));
    }
    if ($this->isNumber($values['num_links']) == FALSE) {
      return drush_set_error('DEVEL_GENERATE_INVALID_INPUT', dt('Invalid number of links'));
    }
    if ($this->isNumber($values['max_depth']) == FALSE || $values['max_depth'] > 9 || $values['max_depth'] < 1) {
      return drush_set_error('DEVEL_GENERATE_INVALID_INPUT', dt('Invalid maximum link depth. Use a value between 1 and 9'));
    }
    if ($this->isNumber($values['max_width']) == FALSE || $values['max_width'] < 1) {
      return drush_set_error('DEVEL_GENERATE_INVALID_INPUT', dt('Invalid maximum menu width. Use a positive numeric value.'));
    }

    return $values;
  }

  /**
   * Deletes custom generated menus
   */
  protected function deleteMenus() {
    if (\Drupal::moduleHandler()->moduleExists('menu_ui')) {
      foreach (menu_ui_get_menus(FALSE) as $menu => $menu_title) {
        if (strpos($menu, 'devel-') === 0) {
          Menu::load($menu)->delete();
        }
      }
    }
    // Delete menu links generated by devel.
    $result = db_select('menu_link_content_data', 'm')
      ->fields('m', array('id'))
      ->condition('m.menu_name', 'devel', '<>')
      // Look for the serialized version of 'devel' => TRUE.
      ->condition('m.options', '%' . db_like('s:5:"devel";b:1') . '%', 'LIKE')
      ->execute()
      ->fetchCol();
    if ($result) {
      entity_delete_multiple('menu_link_content', $result);
    }
  }

  /**
   * Generates new menus.
   */
  protected function generateMenus($num_menus, $title_length = 12) {
    $menus = array();

    if (!\Drupal::moduleHandler()->moduleExists('menu_ui')) {
      $num_menus = 0;
    }

    for ($i = 1; $i <= $num_menus; $i++) {
      $menu = array();
      $menu['label'] = $this->generateWord(mt_rand(2, max(2, $title_length)));
      $menu['id'] = 'devel-' . drupal_strtolower($menu['label']);
      $menu['description'] = t('Description of @name', array('@name' => $menu['label']));
      $new_menu = entity_create('menu', $menu);
      $new_menu->save();
      $menus[$new_menu->id()] = $new_menu->label();
    }

    return $menus;
  }

  /**
   * Generates menu links in a tree structure.
   */
  protected function generateLinks($num_links, $menus, $title_length, $link_types, $max_depth, $max_width) {
    $links = array();
    $menus = array_keys(array_filter($menus));
    $link_types = array_keys(array_filter($link_types));

    $nids = array();
    for ($i = 1; $i <= $num_links; $i++) {
      // Pick a random menu.
      $menu_name = $menus[array_rand($menus)];
      // Build up our link.
      $link_title = $this->generateWord(mt_rand(2, max(2, $title_length)));
      $link = entity_create('menu_link_content', array(
        'menu_name'   => $menu_name,
        'weight'      => mt_rand(-50, 50),
        'title'       => $link_title,
        'bundle'      => 'menu_link_content',
        'description' => t('Description of @title.', array('@title' => $link_title)),
      ));
      $link->setOptions(array('devel' => TRUE));

      // For the first $max_width items, make first level links.
      if ($i <= $max_width) {
        $depth = 0;
      }
      else {
        // Otherwise, get a random parent menu depth.
        $depth = mt_rand(1, max(1, $max_depth - 1));
      }
      // Get a random parent link from the proper depth.
      do {
        $parameters = new MenuTreeParameters();
        $parameters->setMinDepth($depth);
        $parameters->setMaxDepth($depth);
        $tree = $this->menuLinkTree->load($menu_name, $parameters);

        if ($tree) {
          $link->parent = array_rand($tree);
        }
        $depth--;
      } while (!$link->parent && $depth > 0);

      $link_type = array_rand($link_types);
      switch ($link_types[$link_type]) {
        case 'node':
          // Grab a random node ID.
          $select = db_select('node_field_data', 'n')
            ->fields('n', array('nid', 'title'))
            ->condition('n.status', 1)
            ->range(0, 1)
            ->orderRandom();
          // Don't put a node into the menu twice.
          if (!empty($nids[$menu_name])) {
            $select->condition('n.nid', $nids[$menu_name], 'NOT IN');
          }
          $node = $select->execute()->fetchAssoc();
          if (isset($node['nid'])) {
            $nids[$menu_name][] = $node['nid'];
            $link->route_name = 'entity.node.canonical';
            $link->setRouteParameters(array('node' => $node['nid']));
            $link->title = $node['title'];
            break;
          }
        case 'external':
          $link->url = 'http://www.example.com/';
          break;
        case 'front':
          $link->route_name = '<front>';
          break;
        default:
          $link->devel_link_type = $link_type;
          break;
      }

      $link->save();

      $links[$link->id()] = $link->link_title;
    }

    return $links;
  }

}
