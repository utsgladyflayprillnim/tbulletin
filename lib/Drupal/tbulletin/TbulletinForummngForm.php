<?php

namespace Drupal\tbulletin;

use Drupal\system\SystemConfigFormBase;

class TbulletinForummngForm extends SystemConfigFormBase{

  public function getFormID(){
    return 'tbulletin_forummng';
  }

  public function buildForm(array $form, array &$form_state){
    $form['test_forummng_form'] = array(
      '#type' => 'textfield',
      '#title' => 'xxxxx',
      );

    return parent::buildForm( $form, $form_state );
  }

  public function submitForm( array &$form,array &$form_state ){

  }
}
