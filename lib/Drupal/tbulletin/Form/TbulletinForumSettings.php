<?php

namespace Drupal\tbulletin\Form;

use Drupal\system\SystemConfigFormBase;

class TbulletinForumSettings extends SystemConfigFormBase{

  public function getFormID(){
    return 'tbulletin_forum_settings';
  }

  public function buildForm(array $form, array &$form_state){
    $types = node_type_get_names();
    $config = $this->configFactory->get('tbulletin.settings');

    $form['tbulletin_forum_allowed_types'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Content types allowed display as a forum'),
      '#options' => $types,
      '#required' => TRUE,
    );
    if (!is_null($config->get('forum_allowed_types'))){
      $form['tbulletin_forum_allowed_types']['#default_value'] = $config->get('forum_allowed_types');
    }
    return parent::buildForm( $form, $form_state );
  }

  public function submitForm(array &$form,array &$form_state ){
    $this->configFactory->get('tbulletin.settings')
    // Remove unchecked types.
      ->set('forum_allowed_types', $form_state['values']['tbulletin_forum_allowed_types'])
      ->save();
    parent::submitForm($form, $form_state);
  }
}
