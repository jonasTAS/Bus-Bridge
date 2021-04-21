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

/**
 * BBContact.Submit API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   Description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_b_b_contact_Submit_spec(&$spec) {
  $spec['contact'] = array(
    'name' => 'contact',
    'title' => E::ts('Contact data'),
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 1,
    'description' => E::ts('An array of contact data of which the contact_type key is mandatory.'),
  );
  $spec['groups'] = array(
    'name' => 'groups',
    'title' => E::ts('Group data'),
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description' => E::ts('An array of group data.'),
  );
  $spec['activities'] = array(
    'name' => 'activities',
    'title' => E::ts('Activity data'),
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description' => E::ts('An array of activity data.'),
  );
  $spec['want_newsletter'] = array(
    'name' => 'want_newsletter',
    'title' => E::ts('Wants newsletter'),
    'type' => CRM_Utils_Type::T_BOOLEAN,
    'api.required' => 0,
    'api.default' => 1,
    'description' => E::ts('Whether the contact subscribed to the newsletter.'),
  );
}

/**
 * BBContact.Submit API
 *
 * @param array $params
 *
 * @return array API result descriptor
 */
function civicrm_api3_b_b_contact_Submit($params) {
  try {
    // Parse JSON from "contact" and "groups" parameters.
    if (!is_array($params['contact']) && ($params['contact'] = json_decode($params['contact'], JSON_OBJECT_AS_ARRAY)) === NULL) {
      throw new Exception(E::ts('Could not parse parameter "contact".'));
    }
    if (!empty($params['groups'])) {
      if (!is_array($params['groups']) && ($params['groups'] = json_decode($params['groups'], JSON_OBJECT_AS_ARRAY)) === NULL) {
        throw new Exception(E::ts('Could not parse parameter "groups".'));
      }
    }
    if (!empty($params['activities'])) {
      if (!is_array($params['activities']) && ($params['activities'] = json_decode($params['activities'], JSON_OBJECT_AS_ARRAY)) === NULL) {
        throw new Exception(E::ts('Could not parse parameter "activities".'));
      }
    }

    // Check if contact ID already exists
    $contact_is_new = CRM_Bbapi_Submission::isNew($params['contact']);

    // Retrieve contact ID for given contact data.
    $contact_id = CRM_Bbapi_Submission::getContact($params['contact']['contact_type'], $params['contact']);
    // Load contact.
    $contact = civicrm_api3('Contact', 'getsingle', array('id' => $contact_id));

    // Add to group with given status.
    if (!empty($params['groups'])) {
      foreach($params['groups'] as $group_info) {
        list($group_name, $group_status) = explode(':', $group_info) + [NULL, CRM_Bbapi_Submission::GROUP_STATUS_ADDED];
        // This group is the main newsletter for the petition. If the contact
        // accepted the reception of a newsletter, we send a Double OptIn by the
        // MailingEventSubscribe event.
        if (strcasecmp($group_status, CRM_Bbapi_Submission::NEWSLETTER_GROUP) === 0) {
          if ($params['want_newsletter']) {
            // Set group-subscription status to pending
            $mailing_event_subscribe = civicrm_api3('MailingEventSubscribe', 'create', array(
              'contact_id' => $contact_id,
              'email' => $contact['email'],
              'group_id' => CRM_Bbapi_Submission::getGroupIdByName($group_name)
            ));
            $activity = civicrm_api3('Activity', 'create', array(
              'source_contact_id' => $contact_id,
              'activity_type_id' => 'Automatische Email',
              'subject' => 'Double Opt-In für die Gruppe "'.$group_name.'" gesendet.',
              'status_id' => 'Completed'
            ));
          }
        }
        // Pending groups also need a Double-OptIn, but it doesn't depend on
        // accepting the newsletter.
        elseif (strcasecmp($group_status, CRM_Bbapi_Submission::GROUP_STATUS_PENDING) === 0) {
          $mailing_event_subscribe = civicrm_api3('MailingEventSubscribe', 'create', array(
            'contact_id' => $contact_id,
            'email' => $contact['email'],
            'group_id' => CRM_Bbapi_Submission::getGroupIdByName($group_name),
          ));
          $activity = civicrm_api3('Activity', 'create', array(
            'source_contact_id' => $contact_id,
            'activity_type_id' => 'Automatische Email',
            'subject' => 'Double Opt-In für die Gruppe "'.$group_name.'" gesendet.',
            'status_id' => 'Completed'
          ));
        }
        // For some Welcome Journeys, we only want new contacts to join and only
        // in case they just accepted the newsletter.
        elseif (strcasecmp($group_status, CRM_Bbapi_Submission::DOI_NEW_GROUP) === 0) {
          if ($params['want_newsletter'] && $contact_is_new) {
            $group_contact = civicrm_api3('GroupContact', 'create', array(
              'contact_id' => $contact_id,
              'group_id' => CRM_Bbapi_Submission::getGroupIdByName($group_name),
              'status' => 'Added'
            ));
          }
        }
        // All other groups (including the status "Added") will just get added
        // to the contact.
        else {
          $group_contact = civicrm_api3('GroupContact', 'create', array(
            'contact_id' => $contact_id,
            'group_id' => CRM_Bbapi_Submission::getGroupIdByName($group_name),
            'status' => ucfirst($group_status)
          ));
        }
      }
    }

    if (!empty($params['activities'])) {
      foreach($params['activities'] as $activity_info) {
        $new_activity = array(
          'source_contact_id' => $contact_id,
          'activity_type_id' => $activity_info->activity_type_id, //'Double Opt-In bestätigt',
          'subject' => $activity_info->subject, //'Double Opt-In für Newsletter wurde bestätigt',
          'status_id' => 'Completed',
          'created_at' => $activity_info->timestamp,
          'activity_date_time' => date('Y-m-d H:i:s', $activity_info->timestamp) //'2020-01-01 10:10:10'
        );
        $activity = civicrm_api3('Activity', 'create', $new_activity);
      }
    }

    return civicrm_api3_create_success(
      array(
        'Contact' => ['id' => $contact['id']],
        'MailingEventSubscribe' => isset($mailing_event_subscribe['id']) ? ['id' => $mailing_event_subscribe['id']] : NULL,
        'Activity' => isset($activity['id']) ? ['id' => $activity['id']] : NULL,
        'GroupContact' => isset($group_contact['id']) ? ['id' => $group_contact['id']] : NULL,
      ),
      $params
    );
  }
  catch (Exception $exception) {
    return civicrm_api3_create_error($exception->getMessage());
  }
}
