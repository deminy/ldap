<?php

/**
 * @file
 * This class represents a ldap_user module's configuration
 * It is extended by LdapUserConfAdmin for configuration and other admin functions
 */

require_once('ldap_user.module');

class LdapUserConf {

  public $drupalAcctProvisionServer = LDAP_USER_NO_SERVER_SID;  // servers used for to drupal acct provisioning keyed on $sid => boolean
  public $ldapEntryProvisionServer = LDAP_USER_NO_SERVER_SID;  // servers used for provisioning to ldap keyed on $sid => boolean
  public $drupalAcctProvisionEvents = array(LDAP_USER_DRUPAL_USER_CREATE_ON_LOGON, LDAP_USER_DRUPAL_USER_CREATE_ON_MANUAL_ACCT_CREATE);
  public $ldapEntryProvisionEvents = array();
  public $userConflictResolve = LDAP_USER_CONFLICT_RESOLVE_DEFAULT;
  public $acctCreation = LDAP_USER_ACCT_CREATION_LDAP_BEHAVIOR_DEFAULT;
  public $inDatabase = FALSE;
  public $manualAccountConflict = LDAP_USER_MANUAL_ACCT_CONFLICT_REJECT;
  public $setsLdapPassword = TRUE; // @todo default to FALSE and check for mapping to set to true
  public $loginConflictResolve = FALSE;
  /**
   * array of field synch mappings provided by all modules (via hook_ldap_user_attrs_list_alter())
   * array of the form: array(
   * LDAP_USER_SYNCH_DIRECTION_TO_DRUPAL_USER | LDAP_USER_SYNCH_DIRECTION_TO_LDAP_ENTRY => array(
   *   <server_id> => array(
   *     'sid' => <server_id> (redundant)
   *     'ldap_attr' => e.g. [sn]
   *     'user_attr'  => e.g. [field.field_user_lname] (when this value is set to 'user_tokens', 'user_tokens' value is used.)
   *     'user_tokens' => e.g. [field.field_user_lname], [field.field_user_fname] 
   *     'convert' => 1|0 boolean indicating need to covert from binary
   *     'direction' => LDAP_USER_SYNCH_DIRECTION_TO_DRUPAL_USER | LDAP_USER_SYNCH_DIRECTION_TO_LDAP_ENTRY (redundant)
   *     'config_module' => 'ldap_user'
   *     'synch_module' => 'ldap_user'
   *     'enabled' => 1|0 boolean
   *     'contexts'  => array( of LDAP_USER_SYNCH_CONTEXT_* constants indicating when synching should occur)
   *    )
   *  )
   */
  public $synchMapping = NULL; // array of field synching directions for each operation.  should include ldapUserSynchMappings
  // keyed on direction => sid => property, ldap, or field token such as '[field.field_lname] with brackets in them.
  
  /** 
  * synch mappings configured in ldap user module (not in other modules)
  *   array of the form: array(
    LDAP_USER_SYNCH_DIRECTION_TO_DRUPAL_USER | LDAP_USER_SYNCH_DIRECTION_TO_LDAP_ENTRY => array(
      <server_id> => array(
        'sid' => <server_id> (redundant)
        'ldap_attr' => e.g. [sn]
        'user_attr'  => e.g. [field.field_user_lname] (when this value is set to 'user_tokens', 'user_tokens' value is used.)
        'user_tokens' => e.g. [field.field_user_lname], [field.field_user_fname] 
        'convert' => 1|0 boolean indicating need to covert from binary
        'direction' => LDAP_USER_SYNCH_DIRECTION_TO_DRUPAL_USER | LDAP_USER_SYNCH_DIRECTION_TO_LDAP_ENTRY (redundant)
        'config_module' => 'ldap_user'
        'synch_module' => 'ldap_user'
        'enabled' => 1|0 boolean
        'contexts'  => array( of LDAP_USER_SYNCH_CONTEXT_* constants indicating when synching should occur)
      )
    )
  )
  */
  public $ldapUserSynchMappings = NULL;  // 
  // keyed on property, ldap, or field token such as '[field.field_lname] with brackets in them.
  public $detailedWatchdog = FALSE;
  public $provisionsDrupalAccountsFromLdap = FALSE;
  public $provisionsLdapEntriesFromDrupalUsers = FALSE;

  public $wsKey = NULL;
  public $wsEnabled = 0;
  public $wsUserIps = array();
  public $wsActions = array();


  public $synchTypes = NULL; // array of synch types (keys) and user friendly names (values)

  public $wsActionsOptions = array(
    'create' => 'create: Create User Account.',
    'synch' => 'synch: Synch User Account with Current LDAP Data.',
    'disable' => 'disable: Disable User Account.',
    'delete' => 'delete: Remove User Account.',
  );

  public $wsActionsContexts = array(
    'create' => LDAP_USER_SYNCH_CONTEXT_INSERT_DRUPAL_USER,
    'synch' => LDAP_USER_SYNCH_CONTEXT_UPDATE_DRUPAL_USER,
    'disable' => LDAP_USER_SYNCH_CONTEXT_DISABLE_DRUPAL_USER,
    'delete' => LDAP_USER_SYNCH_CONTEXT_DELETE_DRUPAL_USER,
  );

  public $saveable = array(
    'drupalAcctProvisionServer',
    'ldapEntryProvisionServer',
    'drupalAcctProvisionEvents',
    'ldapEntryProvisionEvents',
    'userConflictResolve',
    'manualAccountConflict',
    'acctCreation',
    'ldapUserSynchMappings',
    'wsKey',
    'wsEnabled',
    'wsUserIps',
    'wsActions',
  );

function __construct() {
    $this->load();
   ////dpm('filter');//dpm(array_filter(array_values($this->drupalAcctProvisionEvents)));
    $this->provisionsDrupalAccountsFromLdap = (count(array_filter(array_values($this->drupalAcctProvisionEvents))) > 0);
    $this->provisionsLdapEntriesFromDrupalUsers = (
      $this->ldapEntryProvisionServer
      && $this->ldapEntryProvisionServer != LDAP_USER_NO_SERVER_SID
      && (count(array_filter(array_values($this->ldapEntryProvisionEvents))) > 0)
      );
    $this->synchTypes = array(
      LDAP_USER_SYNCH_CONTEXT_INSERT_DRUPAL_USER => t('On User Creation'),
      LDAP_USER_SYNCH_CONTEXT_UPDATE_DRUPAL_USER => t('On User Update/Save'),
      LDAP_USER_SYNCH_CONTEXT_AUTHENTICATE_DRUPAL_USER => t('On User Logon'),
      LDAP_USER_SYNCH_CONTEXT_CRON => t('Via Cron Batch'),
    );
    $this->setSynchMapping(TRUE);
    $this->detailedWatchdog = variable_get('ldap_help_watchdog_detail', 0);
   
  }

  function load() {

    if ($saved = variable_get("ldap_user_conf", FALSE)) {
      $this->inDatabase = TRUE;
      foreach ($this->saveable as $property) {
        if (isset($saved[$property])) {
          $this->{$property} = $saved[$property];
        }
      }
    }
    else {
      $this->inDatabase = FALSE;
    }

    // determine account creation configuration
    $user_register = variable_get('user_register', USER_REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL);
    if ($this->acctCreation == LDAP_USER_ACCT_CREATION_LDAP_BEHAVIOR_DEFAULT || $user_register == USER_REGISTER_VISITORS) {
      $this->createLDAPAccounts = TRUE;
      $this->createLDAPAccountsAdminApproval = FALSE;
    }
    elseif ($user_register == USER_REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL) {
      $this->createLDAPAccounts = FALSE;
      $this->createLDAPAccountsAdminApproval = TRUE;
    }
    else {
      $this->createLDAPAccounts = FALSE;
      $this->createLDAPAccountsAdminApproval = FALSE;
    }
  }

  /**
   * Destructor Method
   */
  function __destruct() { }


  /**
   * Util to fetch mappings for a given server id
   *
   * @param string $sid
   *   The server id
   * @param string $direction LDAP_USER_SYNCH_DIRECTION_* constant
   * @param string $synch_context is LDAP_USER_SYNCH_CONTEXT_* constant
   *
   * @return array/bool
   *   Array of mappings that may be empty
  */
  private function getSynchMappings($sid, $direction = LDAP_USER_SYNCH_DIRECTION_ALL, $synch_context = LDAP_USER_SYNCH_CONTEXT_NONE) {
  // debug('this.getSynchMappings,$sid='. $sid .',direction='. $direction); debug($this->ldapUserSynchMappings);

    $mappings = array();
    if ($direction == LDAP_USER_SYNCH_DIRECTION_ALL) {
      $directions = array(LDAP_USER_SYNCH_DIRECTION_TO_DRUPAL_USER, LDAP_USER_SYNCH_DIRECTION_TO_LDAP_ENTRY);
    }
    else {
      $directions = array($direction);
    }
    foreach ($directions as $direction) {
      if (!empty($this->ldapUserSynchMappings[$direction][$sid]) && is_array($this->ldapUserSynchMappings[$direction][$sid]) ) {
        foreach ($this->ldapUserSynchMappings[$direction][$sid] as $attribute => $mapping) {
          if (in_array($synch_context, $mapping['contexts'])) {
            $mappings[$attribute] = $mapping;
          }
        }       
      }
    }
    return $mappings;
  }

  public function isDrupalAcctProvisionServer($sid) {
    return (boolean)($this->drupalAcctProvisionServer == $sid);
  }
  
  public function isLdapEntryProvisionServer($sid) {
    return (boolean)($this->ldapEntryProvisionServer == $sid);
  }
  
  /**
   * Util to fetch attributes required for this user conf, not other modules.
   *
   * @param int $synch_context
   *   Any valid sync context constant.
   * @param enum $direction LDAP_USER_SYNCH_DIRECTION_* constants
   * 
  */
  public function getRequiredAttributes($direction = LDAP_USER_SYNCH_DIRECTION_ALL, $synch_context = LDAP_USER_SYNCH_CONTEXT_ALL) {
    $attributes_map = array();
    if ($this->drupalAcctProvisionServer != LDAP_USER_NO_SERVER_SID) {
      $attributes_map = $this->getSynchMappings($this->drupalAcctProvisionServer, $direction, $synch_context);
      foreach ($attributes_map as $detail) {
        // Make sure the mapping is relevant to this context.
        if (in_array($synch_context, $detail['contexts'])) {
          // Add the attribute to our array.
          if ($detail['ldap_attr']) {
            ldap_servers_token_extract_attributes($attributes_map,  $detail['ldap_attr']);
          }
        }
        else {
        }
      }
    }
    return $attributes_map;
  }



  /**
    derive mapping array from ldap user configuration and other configurations.
    if this becomes a resource hungry function should be moved to ldap_user functions
    and stored with static variable. should be cached also.

    this should be cached and modules implementing ldap_user_synch_mapping_alter
    should know when to invalidate cache.

  **/

  function setSynchMapping($reset = TRUE) {  // @todo change default to false after development
    $synch_mapping_cache = cache_get('ldap_user_synch_mapping');
    if (!$reset && $synch_mapping_cache) {
      $this->synchMapping = $synch_mapping_cache->data;
    }
    else {
      $available_user_attrs = array();
      foreach (array(LDAP_USER_SYNCH_DIRECTION_TO_DRUPAL_USER, LDAP_USER_SYNCH_DIRECTION_TO_LDAP_ENTRY) as $direction) {
        if ($direction == LDAP_USER_SYNCH_DIRECTION_TO_DRUPAL_USER && $this->drupalAcctProvisionServer != LDAP_USER_NO_SERVER_SID) {
          $sid = $this->drupalAcctProvisionServer;
        }
        elseif ($direction == LDAP_USER_SYNCH_DIRECTION_TO_LDAP_ENTRY && $this->ldapEntryProvisionServer != LDAP_USER_NO_SERVER_SID) {
          $sid = $this->ldapEntryProvisionServer;
        }
        else { // nothing going on here
          continue;
        }
        
        $available_user_attrs[$direction][$sid] = array();
        $ldap_server = ldap_servers_get_servers($sid, NULL, TRUE);

        $params = array(
          'ldap_server' => $ldap_server,
          'ldap_user_conf' => $this,
          'direction' => $direction,
        );
        drupal_alter('ldap_user_attrs_list', $available_user_attrs[$direction][$sid], $params);
      }
    }
    $this->synchMapping = $available_user_attrs;

    cache_set('ldap_user_synch_mapping',  $this->synchMapping);
  }
  
  /**
   * given a context, determine if ldap user configuration supports it.
   *   this is overall, not per field synching configuration
   *   
   * @param enum $synch_context
   * @param enaum $direction LDAP_USER_SYNCH_DIRECTION_TO_DRUPAL_USER or LDAP_USER_SYNCH_DIRECTION_TO_LDAP_ENTRY
   * @param enum 'synch', 'provision', 'delete_ldap_entry', 'delete_drupal_entry', 'cancel_drupal_entry'
   * @return boolean
   */
 // ($ldap_user_conf->contextEnabled(LDAP_USER_SYNCH_CONTEXT_INSERT_DRUPAL_USER, LDAP_USER_SYNCH_DIRECTION_TO_LDAP_ENTRY, 'provision')) 
  public function contextEnabled($synch_context, $direction, $action = 'synch') {
    
    $result = FALSE;
 
    if ($direction == LDAP_USER_SYNCH_DIRECTION_TO_LDAP_ENTRY && $this->ldapEntryProvisionServer != LDAP_USER_NO_SERVER_SID) {
  //   //dpm('LDAP_USER_SYNCH_DIRECTION_TO_LDAP_ENTRY');//dpm($this->ldapEntryProvisionEvents);
      $configurations = array();
      if ($action == 'synch') {
        $configurations = array(
          LDAP_USER_LDAP_ENTRY_UPDATE_ON_USER_UPDATE,
          LDAP_USER_LDAP_ENTRY_UPDATE_ON_USER_AUTHENTICATE,
        );
      }
      elseif ($action == 'provision') {
        $configurations = array(
          LDAP_USER_LDAP_ENTRY_CREATE_ON_USER_STATUS_IS_1,
          LDAP_USER_LDAP_ENTRY_CREATE_ON_USER_UPDATE,
          LDAP_USER_LDAP_ENTRY_CREATE_ON_USER_AUTHENTICATE,
        );
      }
      elseif ($action == 'delete_ldap_entry') {
        $configurations = array(LDAP_USER_LDAP_ENTRY_DELETE_ON_USER_DELETE);
      }
    // //dpm("configurations");//dpm($configurations);
      $result = count(array_intersect($configurations, array_values(array_filter($this->ldapEntryProvisionEvents))));
      if (!$result) {
      // dpm("contextEnabled: $synch_context, $direction, $action, result=$result"); dpm("configurations");
        //dpm($configurations); dpm("this->ldapEntryProvisionEvents"); dpm($this->ldapEntryProvisionEvents);
      }
    }
    elseif ($this->drupalAcctProvisionServer != LDAP_USER_NO_SERVER_SID) { // default to LDAP_USER_SYNCH_DIRECTION_TO_DRUPAL_USER
    // //dpm('LDAP_USER_SYNCH_DIRECTION_TO_DRUPAL_USER');//dpm($this->drupalAcctProvisionEvents);
      $configurations = array();
      if ($action == 'synch') {
        $configurations = array(
          LDAP_USER_DRUPAL_USER_UPDATE_ON_USER_AUTHENTICATE,
          LDAP_USER_DRUPAL_USER_UPDATE_ON_USER_UPDATE,
        );
      }
      elseif ($action == 'provision') {
        $configurations = array(
          LDAP_USER_DRUPAL_USER_CREATE_ON_MANUAL_ACCT_CREATE,
          LDAP_USER_DRUPAL_USER_CREATE_ON_ALL_USER_CREATION,
          LDAP_USER_DRUPAL_USER_UPDATE_ON_USER_AUTHENTICATE,
        );
      }
      elseif ($action == 'delete_drupal_entry') {
        $configurations = array(
          LDAP_USER_DRUPAL_USER_DELETE_ON_LDAP_ENTRY_MISSING,
        );
      }
      elseif ($action == 'cancel_drupal_entry') {
        $configurations = array(
          LDAP_USER_DRUPAL_USER_CANCEL_ON_LDAP_ENTRY_MISSING,
        );
      }
      //dpm($configurations);//dpm($this->drupalAcctProvisionEvents);
      $result = count(array_intersect($configurations, array_keys(array_filter($this->drupalAcctProvisionEvents))));
     // if (!$result) {
     // dpm("contextEnabled: $synch_context, $direction, $action, result=$result"); dpm("configurations"); dpm($configurations); dpm("this->drupalAcctProvisionEvents"); dpm($this->drupalAcctProvisionEvents);
     // }
    }

    return $result;
  }

 /**
   * given a drupal account, provision an ldap entry if none exists.  if one exists do nothing
   *
   * @param object $account drupal account object with minimum of name property
   * @param int $synch_context (see LDAP_USER_SYNCH_CONTEXT_* constants)
   * @param array $ldap_user as prepopulated ldap entry.  usually not provided
   *
   * @return array of form:
   *     array('status' => 'success', 'fail', or 'conflict'),
   *     array('ldap_server' => ldap server object),
   *     array('proposed' => proposed ldap entry),
   *     array('existing' => existing ldap entry),
   *     array('description' = > blah blah)
   *
   */

  public function provisionLdapEntry($account, $synch_context = LDAP_USER_SYNCH_CONTEXT_INSERT_DRUPAL_USER, $ldap_user, $test_query = FALSE) {
    //dpm('provisionLdapEntry account'); dpm($account);
    $watchdog_tokens = array();
    $result = array(
      'status' => NULL,
      'ldap_server' => NULL,
      'proposed' => NULL,
      'existing' => NULL,
      'description' => NULL,
    );

    list($account, $user_entity) = ldap_user_load_user_acct_and_entity($account->name);
    
    if (is_object($account) && property_exists($account, 'uid') && $account->uid == 1) {
      $result['status'] = 'fail';
      $result['error_description'] = 'can not provision drupal user 1';
      return $result; // do not provision or synch user 1
    }
  
   // dpm('provisionLdapEntry account2'); dpm($account);
    if ($account == FALSE || $account->uid == 0) {
      $result['status'] = 'fail';
      $result['error_description'] = 'can not provision ldap user unless corresponding drupal account exists first.';
      return $result;      
    }
        
    if ($this->ldapEntryProvisionServer == LDAP_USER_NO_SERVER_SID || !$this->ldapEntryProvisionServer) {
      $result['status'] = 'fail';
      $result['error_description'] = 'no provisioning server enabled';
      return $result;
    }
    
    $ldap_server = ldap_servers_get_servers($this->ldapEntryProvisionServer, NULL, TRUE);
    $params = array(
      'direction' => LDAP_USER_SYNCH_DIRECTION_TO_LDAP_ENTRY,
      'synch_context' => $synch_context,
      'module' => 'ldap_user',
      'function' => 'provisionLdapEntry',
      'include_count' => FALSE,
    );
   
    list($proposed_ldap_entry, $error) = $this->drupalUserToLdapEntry($account, $ldap_server, $ldap_user, $params);
    $dn_derived = (is_array($proposed_ldap_entry) && isset($proposed_ldap_entry['dn']) && $proposed_ldap_entry['dn']);
    $existing_ldap_entry = ($dn_derived) ? $ldap_server->dnExists($proposed_ldap_entry['dn']) : NULL;
    
    if ($error == LDAP_USER_PROVISION_RESULT_NO_PWD) {
      $result['status'] = 'fail';
      $result['description'] = 'Can not provision ldap account without user provided password.';
      $result['existing'] = $existing_ldap_entry;
      $result['proposed'] = $proposed_ldap_entry;
      $result['ldap_server'] = $ldap_server;        
    }    
    elseif (!$dn_derived) {
      $result['status'] = 'fail';
      $result['description'] = t('failed to derive dn and or mappings');
      return $result;
    }
    elseif ($existing_ldap_entry) {
      $result['status'] = 'conflict';
      $result['description'] = 'can not provision ldap entry because exists already';
      $result['existing'] = $existing_ldap_entry;
      $result['proposed'] = $proposed_ldap_entry;
      $result['ldap_server'] = $ldap_server;
    }
    elseif ($test_query) {
      $result['status'] = 'fail';
      $result['description'] = 'not created because flagged as test query';
      $result['proposed'] = $proposed_ldap_entry;
      $result['ldap_server'] = $ldap_server;        
    }
    elseif ($ldap_entry_created = $ldap_server->createLdapEntry($proposed_ldap_entry)) {
     // dpm('successfully called createLdapEntry'); dpm($proposed_ldap_entry);
      $result['status'] = 'success';
      $result['description'] = 'ldap account created';
      $result['proposed'] = $proposed_ldap_entry;
      $result['created'] = $ldap_entry_created;
      $result['ldap_server'] = $ldap_server;
      ldap_user_ldap_provision_semaphore('provision', 'user_action_mark' , $account->name);
      // need to store <sid>|<dn> in ldap_user_prov_entries field, which may contain more than one
      $ldap_user_prov_entry = $ldap_server->sid . '|' . $proposed_ldap_entry['dn'];
      if (!isset($user_entity->ldap_user_prov_entries['und'])) {
        $user_entity->ldap_user_prov_entries = array('und' => array());
      }
      $ldap_user_prov_entry_exists = FALSE;
      foreach ($user_entity->ldap_user_prov_entries['und'] as $i => $field_value_instance) {
        if ($field_value_instance == $ldap_user_prov_entry) {
          $ldap_user_prov_entry_exists = TRUE;
        }   
      }
      if (!$ldap_user_prov_entry_exists) {
        $user_entity->ldap_user_prov_entries['und'][] = array(
          'value' =>  $ldap_user_prov_entry,
          'format' => NULL,
          'save_value' => $ldap_user_prov_entry,
        );
        $edit = array(
          'ldap_user_prov_entries' => $user_entity->ldap_user_prov_entries,
        );
        $account = user_load($account->uid);
        $account = user_save($account, $edit);
      }
        
    }
    else {
      $result['status'] = 'fail';
      $result['proposed'] = $proposed_ldap_entry;
      $result['created'] = $ldap_entry_created;
      $result['ldap_server'] = $ldap_server;
      $result['existing'] = NULL;
    }

    $tokens = array(
      '%dn' => isset($result['proposed']['dn']) ? $result['proposed']['dn'] : NULL,
      '%sid' => (isset($result['ldap_server']) && $result['ldap_server']) ? $result['ldap_server']->sid : LDAP_USER_NO_SERVER_SID,
      '%username' => @$account->name,
      '%uid' => @$account->uid,
      '%description' => @$result['description'],
    );
    if (!$test_query && isset($result['status'])) {
      if ($result['status'] == 'success') {
        watchdog('ldap_user', 'LDAP entry on server %sid created dn=%dn.  %description. username=%username, uid=%uid', $tokens, WATCHDOG_INFO);
      }
      elseif($result['status'] == 'conflict') {
        watchdog('ldap_user', 'LDAP entry on server %sid not created because of existing ldap entry. %description. username=%username, uid=%uid', $tokens, WATCHDOG_WARNING);
      }
      elseif($result['status'] == 'fail') {
        watchdog('ldap_user', 'LDAP entry on server %sid not created because error.  %description. username=%username, uid=%uid', $tokens, WATCHDOG_ERROR);
      }
    }
    return $result;
  }


  /**
   * given a drupal account, synch to related ldap entry
   *
   * @param drupal user object $account.  Drupal user object
   * @param array $user_edit.  Edit array for user_save.  generally null unless user account is being created or modified in same synching
   * @param string $synch_context.
   * @param array $ldap_user.  current ldap data of user. see README.developers.txt for structure
   *
   * @return TRUE on success or FALSE on fail.
   */

  public function synchToLdapEntry($account, $user_edit = NULL, $synch_context, $ldap_user =  array(), $test_query = FALSE) {
//    dpm("synchToLdapEntry, synch_context=$synch_context, test_query=". (int)$test_query); dpm($account);dpm($user_edit); 
    //ldap_servers_debug('synchToLdapEntry'); ldap_servers_debug($account); ldap_servers_debug($user_edit);

    if (is_object($account) && property_exists($account, 'uid') && $account->uid == 1) {
      return FALSE; // do not provision or synch user 1
    }
    
    $watchdog_tokens = array();
    $result = FALSE;
    $proposed_ldap_entry = FALSE;
    
    if ($this->ldapEntryProvisionServer != LDAP_USER_NO_SERVER_SID) {
      $ldap_server = ldap_servers_get_servers($this->ldapEntryProvisionServer, NULL, TRUE);
      $params = array(
        'direction' => LDAP_USER_SYNCH_DIRECTION_TO_LDAP_ENTRY,
        'synch_context' => $synch_context,
        'module' => 'ldap_user',
        'function' => 'synchToLdapEntry',
        'include_count' => FALSE,
        'synch_context' => $synch_context,
      );

      list($proposed_ldap_entry, $error) = $this->drupalUserToLdapEntry($account, $ldap_server, $ldap_user, $params);
      
      if ($error != LDAP_USER_PROVISION_RESULT_NO_ERROR) {
        $result = FALSE;
      }
      elseif (is_array($proposed_ldap_entry) && isset($proposed_ldap_entry['dn'])) {
        $existing_ldap_entry = $ldap_server->dnExists($proposed_ldap_entry['dn']);
        $attributes = array(); // this array represents attributes to be modified; not comprehensive list of attributes
        foreach ($proposed_ldap_entry as $attr_name => $attr_values) {
          if ($attr_name != 'dn') {
            if (isset($attr_values['count'])) {
              unset($attr_values['count']);
            }
            if (count($attr_values) == 1) {
              $attributes[$attr_name] = $attr_values[0];
            }
            else {
              $attributes[$attr_name] = $attr_values;
            }
          }   
        }
  //     //dpm('synchToLdapEntry:attributes passed to modifyLdapEntry, dn='. $proposed_ldap_entry['dn']);//dpm($attributes);
        if ($test_query) {
          $proposed_ldap_entry = $attributes;
          $proposed_ldap_entry['dn'] = $proposed_ldap_entry['dn'];
          $result = array(
            'proposed' => $proposed_ldap_entry,
            'server' => $ldap_server,
          );
        }
        else {
         // dpm('modifyLdapEntry,dn=' . $proposed_ldap_entry['dn']);  dpm($attributes);
          $result = $ldap_server->modifyLdapEntry($proposed_ldap_entry['dn'], $attributes);
          if ($result) {
            ldap_user_ldap_provision_semaphore('synch', 'user_action_mark' , $account->name);
          }
        }
      }
      else { // failed to get acceptable proposed ldap entry
        $result = FALSE;
      }
      

      //  $attributes["attribute1"] = "value";
     //   $attributes["attribute2"][0] = "value1";
      //  $attributes["attribute2"][1] = "value2";
    }
   ////dpm('provisionLdapEntry:results');//dpm($results);


    $tokens = array(
      '%dn' => isset($result['proposed']['dn']) ? $result['proposed']['dn'] : NULL,
      '%sid' => $this->ldapEntryProvisionServer,
      '%username' => $account->name,
      '%uid' => ($test_query || !property_exists($account, 'uid')) ? '' : $account->uid,
    );

    if ($result) {
      watchdog('ldap_user', 'LDAP entry on server %sid synched dn=%dn. username=%username, uid=%uid', array(), WATCHDOG_INFO);
    }
    else {
      watchdog('ldap_user', 'LDAP entry on server %sid not synched because error. username=%username, uid=%uid', array(), WATCHDOG_ERROR);
    }

    return $result;
  
  }

  /**
   * given a drupal account, query ldap and get all user fields and create user account
   *
   * @param array $account drupal account array with minimum of name
   * @param array $user_edit drupal edit array in form user_save($account, $user_edit) would take,
   *   generally empty unless overriding synchToDrupalAccount derived values
   * @param int synch_context (see LDAP_USER_SYNCH_CONTEXT_* constants)
   * @param array $ldap_user as user's ldap entry.  passed to avoid requerying ldap in cases where already present
   * @param boolean $save indicating if drupal user should be saved.  generally depends on where function is called from.
   *
   * @return result of user_save() function is $save is true, otherwise return TRUE
   *   $user_edit data returned by reference
   *
   */
  public function synchToDrupalAccount($drupal_user, &$user_edit, $synch_context, $ldap_user = NULL, $save = FALSE) {
  //  dpm('synchToDrupalAccount');
    $debug = array(
      'account' => $drupal_user,
      'user_edit' => $user_edit,
      'ldap_user' => $ldap_user,
      'synch_context' => $synch_context,
    );

    if (
        (!$ldap_user  && !isset($drupal_user->name)) ||
        (!$drupal_user && $save) ||
        ($ldap_user && !isset($ldap_user['sid']))
    ) {
       // should throw watchdog error also
       return FALSE;
    }

    if (!$ldap_user && $this->drupalAcctProvisionServer != LDAP_USER_NO_SERVER_SID) {
      $ldap_user = ldap_servers_get_user_ldap_data($drupal_user->name, $this->drupalAcctProvisionServer, LDAP_USER_SYNCH_DIRECTION_TO_DRUPAL_USER, $synch_context);
    }

    if (!$ldap_user) {
      return FALSE;
    }
 
    if ($this->drupalAcctProvisionServer != LDAP_USER_NO_SERVER_SID) {
      $ldap_server = ldap_servers_get_servers($this->drupalAcctProvisionServer, NULL, TRUE);
      $this->entryToUserEdit($ldap_user, $user_edit, $ldap_server, LDAP_USER_SYNCH_DIRECTION_TO_DRUPAL_USER, $synch_context);
    }

    if ($save) {
     // $account = new stdClass();
      $account = user_load($drupal_user->uid);
      $result = user_save($account, $user_edit, 'ldap_user');
      return $result;
    }
    else {
      return TRUE;
    }
  }


  /**
   * given a drupal account, delete user account
   *
   * @param string $username drupal account name
   * @param int synch_context (see LDAP_USER_SYNCH_CONTEXT_* constants)
   *
   * @return TRUE or FALSE.  FALSE indicates failed or action not enabled in ldap user configuration
   */
  public function deleteDrupalAccount($username, $synch_context) {
    // @todo check if deletion allowed/enabled in context
    $user = user_load_by_name($username);
    if (is_object($user)) {
      user_delete($user->uid);
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * given a drupal account, find the account that would or has been provisioned
   *
   * @param drupal user object $account
   *
   * @return FALSE or ldap entry
   */ 
  public function getProvisionRelatedLdapEntry($account, $synch_context = LDAP_USER_SYNCH_CONTEXT_ALL) {
  
    if (!$sid = $this->ldapEntryProvisionServer) {
      return FALSE;
    }
    // $user_entity->ldap_user_prov_entries,
    $ldap_server = ldap_servers_get_servers($sid, NULL, TRUE);
    $params = array(
      'direction' => LDAP_USER_SYNCH_DIRECTION_TO_LDAP_ENTRY,
      'synch_context' => $synch_context,
      'module' => 'ldap_user',
      'function' => 'getProvisionRelatedLdapEntry',
      'include_count' => FALSE,
      );
    list($proposed_ldap_entry, $error) = $this->drupalUserToLdapEntry($account, $ldap_server, NULL, $params);

    if (!(is_array($proposed_ldap_entry) && isset($proposed_ldap_entry['dn']) && $proposed_ldap_entry['dn'])) {
      return FALSE;
    }
    return $ldap_server->dnExists($proposed_ldap_entry['dn'], 'ldap_entry');
    
  }
 
  /**
   * given a drupal account, delete ldap entry that was provisioned based on it
   *   normally this will be 0 or 1 entry, but the ldap_user_provisioned_ldap_entries
   *   field attached to the user entity track each ldap entry provisioned
   *
   * @param string $username drupal account name
   * @param int synch_context (see LDAP_USER_SYNCH_CONTEXT_* constants)
   *
   * @return TRUE or FALSE.  FALSE indicates failed or action not enabled in ldap user configuration
   */
  public function deleteProvisionedLdapEntries($account) {
    // determine server that is associated with user
    $boolean_result = FALSE;
    list($account, $user_entity) = ldap_user_load_user_acct_and_entity($account->name);

    $language = ($account->language) ? $account->language : 'und';
    if (isset($account->ldap_user_prov_entries[$language][0])) {
      foreach ($account->ldap_user_prov_entries[$language] as $i => $field_instance) {
        $parts = explode('|', $field_instance['value']);
        if (count($parts) == 2) {

          list($sid, $dn) = $parts;
          $ldap_server = ldap_servers_get_servers($sid, NULL, TRUE);
          if (is_object($ldap_server) && $dn) {

            $boolean_result = $ldap_server->delete($dn);
            $tokens = array('%sid' => $sid, '%dn' => $dn, '%username' => $account->name, '%uid' => $account->uid);
            if ($boolean_result) {
              watchdog('ldap_user', 'LDAP entry on server %sid deleted dn=%dn. username=%username, uid=%uid', $tokens, WATCHDOG_INFO);
            }
            else {
              watchdog('ldap_user', 'LDAP entry on server %sid not deleted because error. username=%username, uid=%uid', $tokens, WATCHDOG_ERROR);
            }
          }
          else {
            $boolean_result = FALSE;
          }
        }
      }
    }
    return $boolean_result;

  }

/** populate ldap entry array
   *
   * @param array $account drupal account
   * @param object $ldap_server
   * @param array $ldap_user ldap entry of user, returned by reference
   * @param array $params with the following key values:
   *    'synch_context' => LDAP_USER_SYNCH_CONTEXT_* constant
        'module' => module calling function, e.g. 'ldap_user'
        'function' => function calling function, e.g. 'provisionLdapEntry'
        'include_count' => should 'count' array key be included
        'direction' => LDAP_USER_SYNCH_DIRECTION_TO_LDAP_ENTRY || LDAP_USER_SYNCH_DIRECTION_TO_DRUPAL_USER
   *
   * @return ldap entry in ldap extension array format.
   */

  function drupalUserToLdapEntry($account, $ldap_server, $ldap_user_entry = array(), $params = array()) {
   // dpm('ldap_user_entry1'); dpm($ldap_user_entry);
   //dpm('call to drupalUserToLdapEntry, account:'); dpm($account); dpm('params'); dpm($params); dpm('ldap_user_entry');dpm($ldap_user_entry);
    $provision = (isset($params['function']) && $params['function'] == 'provisionLdapEntry');
    $result = LDAP_USER_PROVISION_RESULT_NO_ERROR;
    
    if (!is_object($account) || !is_object($ldap_server)) {
      return array(NULL, LDAP_USER_PROVISION_RESULT_BAD_PARAMS);
    }
    $watchdog_tokens = array(
      '%drupal_username' => $account->name,
    );
    $include_count = (isset($params['include_count']) && $params['include_count']);
    $synch_context = isset($params['synch_context']) ? $params['synch_context'] : LDAP_USER_SYNCH_CONTEXT_ALL;
    $direction = isset($params['direction']) ? $params['direction'] : LDAP_USER_SYNCH_DIRECTION_ALL;
    $mappings = $this->getSynchMappings($ldap_server->sid, $direction, $synch_context);

      // Loop over the mappings.
    foreach ($mappings as $field_key => $field_detail) {
      list($ldap_attr_name, $ordinal, $source_data_type, $target_data_type) = ldap_servers_token_extract_parts($field_key, TRUE);  //trim($field_key, '[]');
      $ordinal = (!$ordinal) ? 0 : $ordinal;
      if (isset($ldap_user_entry[$ldap_attr_name]) && is_array($ldap_user_entry[$ldap_attr_name]) && isset($ldap_user_entry[$ldap_attr_name][$ordinal]) ) { 
        continue; // don't override values passed in;
      }
      
      if ($params['synch_context'] != LDAP_USER_SYNCH_CONTEXT_ALL) {
        $synched = $this->isSynched($field_key, $ldap_server, $params['synch_context'], LDAP_USER_SYNCH_DIRECTION_TO_LDAP_ENTRY);
      }
      else {
        $synched = TRUE;
      }

      if ($synched) {
        $token = ($field_detail['user_attr'] == 'user_tokens') ? $field_detail['user_tokens'] : $field_detail['user_attr'];
        $value = ldap_servers_token_replace($account, $token, 'user_account');

        if (substr($token, 0, 10) == '[password.' && (!$value || $value == $token)) { // deal with empty/unresolved password
          if (!$provision) {
            continue; //don't overwrite password on synch if no value provided
          }
          elseif ($token == '[password.user]') {
            $result = LDAP_USER_PROVISION_RESULT_NO_PWD; // password.user signifies don't provision if no password available.
          }
          elseif ($provision && $token == '[password.user-none]') {
            continue; // password.user-none signifies don't supply attribute to ldap
          }
        }
        if ($ldap_attr_name == 'dn') {
          $ldap_user_entry['dn'] = $value;
          $ldap_user_entry['distinguishedName'][0] = $value;
          if ($include_count) {
             $ldap_user_entry['distinguishedName']['count'] = 1;
          }         
        }
        else {
          if (!isset($ldap_user_entry[$ldap_attr_name]) || !is_array($ldap_user_entry[$ldap_attr_name])) {
            $ldap_user_entry[$ldap_attr_name] = array();
          }
          $ldap_user_entry[$ldap_attr_name][$ordinal] = $value;
          if ($include_count) {
             $ldap_user_entry[$ldap_attr_name]['count'] = count($ldap_user_entry[$ldap_attr_name]);
          }
         // dpm("ldap_user_entry: $ldap_attr_name=$ldap_attr_name, ordinal=$ordinal"); dpm($ldap_user_entry[$ldap_attr_name]);
        }
      }
    }

    /**
     * 4. call drupal_alter() to allow other modules to alter $ldap_user
     */

    drupal_alter('ldap_entry', $ldap_user_entry, $params);
 
    return array($ldap_user_entry, $result);

  }



   /**
   * given a drupal account, query ldap and get all user fields and save user account
   * (note: parameters are in odd order to match synchDrupalAccount handle)
   *
   * @param array $account drupal account object or null
   * @param array $user_edit drupal edit array in form user_save($account, $user_edit) would take.
   * @param int $synch_context (see LDAP_USER_SYNCH_CONTEXT_* constants)
   * @param array $ldap_user as user's ldap entry.  passed to avoid requerying ldap in cases where already present
   * @param boolean $save indicating if drupal user should be saved.  generally depends on where function is called from and if the
   *
   * @return result of user_save() function is $save is true, otherwise return TRUE on success or FALSE on any problem
   *   $user_edit data returned by reference
   *
   */
// provisionDrupalAccount               ($account,         $user_edit, LDAP_USER_SYNCH_CONTEXT_AUTHENTICATE_DRUPAL_USER, NULL, TRUE)
  public function provisionDrupalAccount($account = FALSE, &$user_edit, $synch_context = LDAP_USER_SYNCH_CONTEXT_INSERT_DRUPAL_USER, $ldap_user = NULL, $save = TRUE) {

    $watchdog_tokens = array();
    /**
     * @todo
     * -- add check in for mail, puid, username, and existing drupal user conflicts
     */

    if (!$account) {
      $account = new stdClass();
    }
    $account->is_new = TRUE;

    if (!$ldap_user && !isset($user_edit['name'])) {
       return FALSE;
    }

    if (!$ldap_user) {
      $watchdog_tokens['%username'] = $user_edit['name'];
      if ($this->drupalAcctProvisionServer != LDAP_USER_NO_SERVER_SID) {
        $ldap_user = ldap_servers_get_user_ldap_data($user_edit['name'], $this->drupalAcctProvisionServer, LDAP_USER_SYNCH_DIRECTION_TO_DRUPAL_USER, $synch_context);
      }
      if (!$ldap_user) {
        if ($this->detailedWatchdog) {
          watchdog('ldap_user', '%username : failed to find associated ldap entry for username in provision.', $watchdog_tokens, WATCHDOG_DEBUG);
        }
        return FALSE;
      }
    }

    if (!isset($user_edit['name']) && isset($account->name)) {
      $user_edit['name'] = $account->name;
      $watchdog_tokens['%username'] = $user_edit['name'];
    }

    
    
    if ($this->drupalAcctProvisionServer != LDAP_USER_NO_SERVER_SID) {
      $ldap_server = ldap_servers_get_servers($this->drupalAcctProvisionServer, 'enabled', TRUE);  // $ldap_user['sid']
      $params = array(
        'account' => $account,
        'user_edit' => $user_edit,
        'synch_context' => $synch_context,
        'module' => 'ldap_user',
        'function' => 'provisionDrupalAccount',
        'direction' => LDAP_USER_SYNCH_DIRECTION_TO_DRUPAL_USER,
      );
      
      drupal_alter('ldap_entry', $ldap_user, $params);
      // look for existing drupal account with same puid.  if so update username and attempt to synch in current context
      $puid = $ldap_server->derivePuidFromLdapEntry($ldap_user['attr']);
      $account2 = ($puid) ? $ldap_server->drupalUserFromPuid($puid) : FALSE;

      if ($account2) { // account exists
        // 1. correct username and authmap
        $account = user_save($account2, $user_edit, 'ldap_user');
        user_set_authmaps($account, array("authname_ldap_user" => $user_edit['name']));
        // 2. attempt synch if appropriate for current context
        if ($account && $this->contextEnabled($synch_context, LDAP_USER_SYNCH_DIRECTION_TO_DRUPAL_USER, 'synch')) {
          $account = $this->synchToDrupalAccount($account, $user_edit, $synch_context, NULL, TRUE);
        }
        $result = ($account) ? $account : $account2;
        return $result;
      }
      else {
      
        $this->entryToUserEdit($ldap_user, $user_edit, $ldap_server, LDAP_USER_SYNCH_DIRECTION_TO_DRUPAL_USER, $synch_context);
  
        if ($save) {
          $account = user_save(NULL, $user_edit, 'ldap_user');
          if (!$account) {
            drupal_set_message(t('User account creation failed because of system problems.'), 'error');
         //   debug(t('User account creation failed because of system problems.'));
          }
          else {
          // //dpm("user save success");//dpm($account);
            user_set_authmaps($account, array('authname_ldap_user' => $user_edit['name']));
          }
          return $account;
        }
        return TRUE;
      }
    }
  }
  
  function ldapAssociateDrupalAccount($drupal_username) {
    if ($this->drupalAcctProvisionServer != LDAP_USER_NO_SERVER_SID) {
      $synch_context = LDAP_USER_SYNCH_CONTEXT_LDAP_ASSOCIATE;
      $ldap_server = ldap_servers_get_servers($this->drupalAcctProvisionServer, 'enabled', TRUE);  // $ldap_user['sid']
      $account = user_load_by_name($drupal_username);
      $ldap_user = ldap_servers_get_user_ldap_data($drupal_username, $this->drupalAcctProvisionServer, LDAP_USER_SYNCH_DIRECTION_TO_DRUPAL_USER, $synch_context);
     // dpm('ldapAssociateDrupalAccount:ldap_user'); dpm($ldap_user);
      $user_edit = array();
      $params = array(
        'account' => $account,
        'user_edit' => $user_edit,
        'synch_context' => $synch_context,
        'module' => 'ldap_user',
        'function' => 'ldapAssociateDrupalAccount',
        'direction' => LDAP_USER_SYNCH_DIRECTION_TO_DRUPAL_USER,
      );
      drupal_alter('ldap_entry', $ldap_user, $params);
      $this->entryToUserEdit($ldap_user, $user_edit, $ldap_server, LDAP_USER_SYNCH_DIRECTION_TO_DRUPAL_USER, $synch_context);
     // dpm('ldapAssociateDrupalAccount:user_edit'); dpm($user_edit);
      $account = user_save($account, $user_edit, 'ldap_user');
    //  dpm('ldapAssociateDrupalAccount:account after user save'); dpm($account);
      if ($account) {
        user_set_authmaps($account, array('authname_ldap_user' => $drupal_username));
      }
    }
  }
  
  /** populate $user edit array (used in hook_user_save, hook_user_update, etc)
   * ... should not assume all attribues are present in ldap entry
   *
   * @param array ldap entry $ldap_user
   * @param object $ldap_server
   * @param array $edit see hook_user_save, hook_user_update, etc
   * @param drupal account object $account
   * @param string $op see hook_ldap_attributes_needed_alter
   */

  function entryToUserEdit($ldap_user, &$edit, $ldap_server, $direction = LDAP_USER_SYNCH_DIRECTION_TO_DRUPAL_USER, $synch_context = LDAP_USER_SYNCH_CONTEXT_ALL) {

    // need array of user fields and which direction and when they should be synched.
    
    $mail_synched = $this->isSynched('[property.mail]', $ldap_server, $synch_context, $direction);
    if (!isset($edit['mail']) && $mail_synched) {
      $derived_mail = $ldap_server->deriveEmailFromLdapEntry($ldap_user['attr']);
      if ($derived_mail) {
        $edit['mail'] = $derived_mail;
      }
    }
    
    if ($this->isSynched('[property.name]', $ldap_server, $synch_context, $direction) && !isset($edit['name'])) {
      $name = $ldap_server->deriveUsernameFromLdapEntry($ldap_user['attr']);
      if ($name) {
        $edit['name'] = $name;
      }
    }

    if ($direction ==  LDAP_USER_SYNCH_DIRECTION_TO_DRUPAL_USER &&
        ($synch_context == LDAP_USER_SYNCH_CONTEXT_INSERT_DRUPAL_USER || $synch_context == LDAP_USER_SYNCH_CONTEXT_ALL)
      ) {
      $edit['mail'] = isset($edit['mail']) ? $edit['mail'] : $ldap_user['mail'];
      $edit['pass'] = isset($edit['pass']) ? $edit['pass'] : user_password(20);
      $edit['init'] = isset($edit['init']) ? $edit['init'] : $edit['mail'];
      $edit['status'] = isset($edit['status']) ? $edit['status'] : 1;
      $edit['signature'] = isset($edit['signature']) ? $edit['signature'] : '';
      
      $edit['data']['ldap_authentication']['init'] = array(
        'sid'  => $ldap_user['sid'],
        'dn'   => $ldap_user['dn'],
        'mail' => $edit['mail'],
      );
    }

    /**
     * basic $user ldap fields
     */
    if ($this->isSynched('[field.ldap_user_puid]', $ldap_server, $synch_context, $direction)) {
      $ldap_user_puid = $ldap_server->derivePuidFromLdapEntry($ldap_user['attr']);
      if ($ldap_user_puid) {
        $edit['ldap_user_puid'][LANGUAGE_NONE][0]['value'] = $ldap_user_puid; //
      }
    }
    if ($this->isSynched('[field.ldap_user_puid_property]', $ldap_server, $synch_context, $direction)) {
      $edit['ldap_user_puid_property'][LANGUAGE_NONE][0]['value'] = $ldap_server->unique_persistent_attr;
    }
    if ($this->isSynched('[field.ldap_user_puid_sid]', $ldap_server, $synch_context, $direction)) {
      $edit['ldap_user_puid_sid'][LANGUAGE_NONE][0]['value'] = $ldap_server->sid;
    }
    if ($this->isSynched('[field.ldap_user_current_dn]', $ldap_server, $synch_context, $direction)) {
      $edit['ldap_user_current_dn'][LANGUAGE_NONE][0]['value'] = $ldap_user['dn'];
    }

    // Get any additional mappings.
    $mappings = $this->getSynchMappings($ldap_server->sid, $direction, $synch_context);
     // Loop over the mappings.
     foreach ($mappings as $user_attr_key => $field_detail) {
     // //dpm('field detail');//dpm($field_detail);
       // Make sure this mapping is relevant to the sync context.
       if (!$this->isSynched($user_attr_key, $ldap_server, $synch_context, $direction)) {
         continue;
       }

       $value = ldap_servers_token_replace($ldap_user['attr'], $field_detail['ldap_attr'], 'ldap_entry');
       list($value_type, $value_name, $value_instance) = ldap_servers_parse_user_attr_name($user_attr_key);

       // $value_instance not used, may have future use case

       // Are we dealing with a field?
       if ($value_type == 'field') {
         // Field api field - first we get the field.
         $field = field_info_field($value_name);
         // Then the columns for the field in the schema.
         $columns = array_keys($field['columns']);
         // Then we convert the value into an array if it's scalar.
         $values = $field['cardinality'] == 1 ? array($value) : (array) $value;

         $items = array();
         // Loop over the values and set them in our $items array.
         foreach ($values as $delta => $value) {
           if (isset($value)) {
             // We set the first column value only, this is consistent with
             // the Entity Api (@see entity_metadata_field_property_set).
             $items[$delta][$columns[0]] = $value;
           }
         }
         // Add them to our edited item.
         $edit[$value_name][LANGUAGE_NONE] = $items;
       }
       elseif ($value_type == 'property') {
         // Straight property.
         $edit[$value_name] = $value;
       }
    }

    // Allow other modules to have a say.

    drupal_alter('ldap_user_edit_user', $edit, $ldap_user, $ldap_server, $synch_context);

  }
  /**
   * given configuration of synching, determine is a given synch should occur
   *
   * @param string $attr_token e.g. [property.mail], [field.ldap_user_puid_property]
   * @param scalar $synch_context (see LDAP_USER_SYNCH_CONTEXT_* constants in ldap_user.module)
   * @param scalar $direction LDAP_USER_SYNCH_DIRECTION_TO_DRUPAL_USER or LDAP_USER_SYNCH_DIRECTION_TO_LDAP_ENTRY
   */

  public function isSynched($attr_token, $ldap_server, $synch_context, $direction) {
    $result = (boolean)(
      isset($this->synchMapping[$direction][$ldap_server->sid][$attr_token]['contexts']) &&
      in_array($synch_context, $this->synchMapping[$direction][$ldap_server->sid][$attr_token]['contexts'])
    );
    return $result;
  }


} // end LdapUserConf class