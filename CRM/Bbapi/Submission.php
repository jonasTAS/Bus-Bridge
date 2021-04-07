<?php
/*------------------------------------------------------------+
| Animal Equality API extension                               |
| Copyright (C) 2019 SYSTOPIA                                 |
| Author: J. Schuppe (schuppe@systopia.de)                    |
+-------------------------------------------------------------+
| This program is released as free software under the         |
| Affero GPL license. You can redistribute it and/or          |
| modify it under the terms of this license which you         |
| can read by viewing the included agpl.txt or online         |
| at www.gnu.org/licenses/agpl.html. Removal of this          |
| copyright header is strictly prohibited without             |
| written permission from the original author(s).             |
+-------------------------------------------------------------*/

use CRM_Bbapi_ExtensionUtil as E;

class CRM_Bbapi_Submission {

  const NEWSLETTER_GROUP = 'Newsletter';

  const GROUP_STATUS_ADDED    = 'Added';

  const GROUP_STATUS_PENDING  = 'Pending';

  const DOI_NEW_GROUP = 'NewAndDoiAccepted';

  /**
   * The default ID of the "Employer of" relationship type.
   */
  const EMPLOYER_RELATIONSHIP_TYPE_ID = 5;

  /**
   * Retrieves the contact matching the given contact data or creates a new
   * contact.
   *
   * @param string $contact_type
   *   The contact type to look for/to create.
   * @param array $contact_data
   *   Data to use for contact lookup/to create a contact with.
   *
   * @return int | NULL
   *   The ID of the matching/created contact, or NULL if no matching contact
   *   was found and no new contact could be created.
   * @throws \CiviCRM_API3_Exception
   *   When invalid data was given.
   */
  public static function getContact($contact_type, $contact_data) {
    // If no parameters are given, do nothing.
    if (empty($contact_data)) {
      return NULL;
    }

    // Prepare values: country.
    if (!empty($contact_data['country'])) {
      if (is_numeric($contact_data['country'])) {
        // If a country ID is given, update the parameters.
        $contact_data['country_id'] = $contact_data['country'];
        unset($contact_data['country']);
      }
      else {
        // Look up the country depending on the given ISO code.
        $country = civicrm_api3('Country', 'get', array('iso_code' => $contact_data['country']));
        if (!empty($country['id'])) {
          $contact_data['country_id'] = $country['id'];
          unset($contact_data['country']);
        }
        else {
          throw new \CiviCRM_API3_Exception(
            E::ts('Unknown country %1.', array(1 => $contact_data['country'])),
            'invalid_format'
          );
        }
      }
    }

    $manager = CRM_Extension_System::singleton()->getManager();
    if ($manager->getStatus('de.systopia.xcm') === CRM_Extension_Manager::STATUS_INSTALLED) {
      // XCM is installed.
      $contact_data['contact_type'] = $contact_type;
      $contact = civicrm_api3('Contact', 'getorcreate', $contact_data);
      $contact_id = (!empty($contact['id']) ? $contact['id'] : NULL);
    }
    else {
      // XCM is not installed. Try to find the contact with Contact.getsingle.
      try {
        $contact = civicrm_api3('Contact', 'getsingle', $contact_data);
        $contact_id = $contact['id'];
      }
      catch (CiviCRM_API3_Exception $exception) {
        // Contact could not be found, create it.
        $contact = civicrm_api3('Contact', 'create', $contact_data);
        $contact_id = $contact['id'];
      }
    }

    return $contact_id;
  }

  public static function getGroupIdByName($group_name) {
    $group = civicrm_api3('Group', 'getsingle', array(
      'name' => $group_name,
      'return' => 'id',
    ));
    return $group['id'];
  }

  /**
   * Check if contact already exists
   *
   * @param $contact_type
   * @param $contact_data
   *
   * @return bool
   */
  public static function isNew($contact_data) {
    $manager = CRM_Extension_System::singleton()->getManager();
    if ($manager->getStatus('de.systopia.xcm') === CRM_Extension_Manager::STATUS_INSTALLED) {
      $profile = CRM_Utils_Array::value('xcm_profile', $contact_data, NULL);
      $engine = CRM_Xcm_MatchingEngine::getEngine($profile);
      $contact = $engine->matchContact($contact_data);
      return empty($contact['contact_id']);
    }
    else {
      try {
        civicrm_api3('Contact', 'getsingle', $contact_data);
        return FALSE;
      }
      catch (CiviCRM_API3_Exception $exception) {
        return TRUE;
      }
    }

  }
  
}
