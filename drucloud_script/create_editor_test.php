<?php

$roles = array('editor');

$new_user = array(
  'uid'     => NULL,
  'name'    => 'editor4',
  'pass'    => '123456',
  'mail'    => 'editor4' . '@example.drucloud.com',
  'status'  => 1,
  //'roles' => array_combine($roles, $roles),
  'roles' => $roles,
);

$account = entity_create('user', $new_user);
$account->save();
