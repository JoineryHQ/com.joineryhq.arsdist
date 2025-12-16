<?php

require_once 'arsdist.civix.php';

use CRM_Arsdist_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function arsdist_civicrm_config(&$config): void {
  _arsdist_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function arsdist_civicrm_install(): void {
  _arsdist_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function arsdist_civicrm_enable(): void {
  _arsdist_civix_civicrm_enable();
}

function arsdist_civicrm_triggerInfo(&$info, $tableName) {
  
//  ALTER TABLE `civicrm_address`
//ADD INDEX `postal_code` (`postal_code`);
  
  $customField = \Civi\Api4\CustomField::get()
  ->setCheckPermissions(FALSE)
  ->addWhere('custom_group_id:name', '=', 'ARS_Contact_Attributes_Calculated_')
  ->addWhere('name', '=', 'Region_District')
  ->setLimit(1)
  ->addChain('custom_group', \Civi\Api4\CustomGroup::get()
    ->setCheckPermissions(FALSE)
    ->setUseCache(TRUE)
    ->addWhere('id', '=', '$custom_group_id'),
  0)
  ->execute()
  ->first();
  $columnName = $customField['column_name'] ?? NULL;
  $tableName = $customField['custom_group']['table_name'] ?? NULL;

  if (empty($tableName) || empty($columnName)) {
    // No such custom field found; do nothing and return.
    return;
  }
  
  $sourceTable = 'civicrm_address';

  $sql = "
    REPLACE INTO `$tableName` (entity_id, $columnName)
    SELECT * FROM (
      SELECT contact_id, district_code
      FROM
      $sourceTable a 
        INNER JOIN civicrm_arsdist_lookup al ON 
          al.state_province_id = a.state_province_id 
          and al.postal_code in ('*', a.postal_code)
      WHERE a.contact_id = NEW.contact_id
        and a.is_primary
    ) as regionlist
    GROUP BY contact_id;
  ";
  $sql_field_parts = array();

  $info[] = array(
      'table' => $sourceTable,
      'when' => 'AFTER',
      'event' => 'INSERT',
      'sql' => $sql,
  );
  $info[] = array(
      'table' => $sourceTable,
      'when' => 'AFTER',
      'event' => 'UPDATE',
      'sql' => $sql,
  );
  // For delete, we reference OLD.contact_id instead of NEW.contact_id
  $sql = str_replace('NEW.contact_id', 'OLD.contact_id', $sql);
  $info[] = array(
      'table' => $sourceTable,
      'when' => 'AFTER',
      'event' => 'DELETE',
      'sql' => $sql,
  );
}