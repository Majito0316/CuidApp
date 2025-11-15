<?php
require_once __DIR__.'/session.php';
function require_login(){
  if (!uid()) { header('Location: login.php'); exit; }
}
function require_role($allowed){
  require_login();
  if (!in_array(rol(), (array)$allowed, true)) {
    header('Location: login.php'); exit;
  }
}
