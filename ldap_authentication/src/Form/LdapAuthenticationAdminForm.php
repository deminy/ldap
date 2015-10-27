<?php

/**
 * @file
 * Contains \Drupal\ldap_authentication\Form\LdapAuthenticationAdminForm.
 */

namespace Drupal\ldap_authentication\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class LdapAuthenticationAdminForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ldap_authentication_admin_form';
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    module_load_include('php', 'ldap_authentication', 'LdapAuthenticationConfAdmin.class');
    $auth_conf = new LdapAuthenticationConfAdmin();
    return $auth_conf->drupalForm();
  }

  public function validateForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {

    ldap_servers_module_load_include('php', 'ldap_authentication', 'LdapAuthenticationConfAdmin.class');
    $auth_conf = new LdapAuthenticationConfAdmin();
    $errors = $auth_conf->drupalFormValidate($form_state->getValues());
    foreach ($errors as $error_name => $error_text) {
      $form_state->setErrorByName($error_name, t($error_text));
    }

  }

  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {

    ldap_servers_module_load_include('php', 'ldap_authentication', 'LdapAuthenticationConfAdmin.class');
    $auth_conf = new LdapAuthenticationConfAdmin();
    $auth_conf->drupalFormSubmit($form_state->getValues()); // add form data to object and save or create
    if (!$auth_conf->hasEnabledAuthenticationServers()) {
      drupal_set_message(t('No LDAP servers are enabled for authentication,
      so no LDAP Authentication can take place.  This essentially disables
      LDAP Authentication.'), 'warning');
    }
    if ($auth_conf->hasError == FALSE) {
      drupal_set_message(t('LDAP Authentication configuration saved'), 'status');
      drupal_goto(LDAP_SERVERS_MENU_BASE_PATH . '/authentication');
    }
    else {
      $form_state->setErrorByName($auth_conf->errorName, $auth_conf->errorMsg);
      $auth_conf->clearError();
    }

  }

}