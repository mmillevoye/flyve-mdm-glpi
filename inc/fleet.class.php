<?php
/**
 LICENSE

Copyright (C) 2016 Teclib'
Copyright (C) 2010-2016 by the FusionInventory Development Team.

This file is part of Flyve MDM Plugin for GLPI.

Flyve MDM Plugin for GLPi is a subproject of Flyve MDM. Flyve MDM is a mobile
device management software.

Flyve MDM Plugin for GLPI is free software: you can redistribute it and/or
modify it under the terms of the GNU Affero General Public License as published
by the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
Flyve MDM Plugin for GLPI is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU Affero General Public License for more details.
You should have received a copy of the GNU Affero General Public License
along with Flyve MDM Plugin for GLPI. If not, see http://www.gnu.org/licenses/.
 ------------------------------------------------------------------------------
 @author    Thierry Bugier Pineau
 @copyright Copyright (c) 2016 Flyve MDM plugin team
 @license   AGPLv3+ http://www.gnu.org/licenses/agpl.txt
 @link      https://github.com/flyvemdm/backend
 @link      http://www.glpi-project.org/
 ------------------------------------------------------------------------------
*/

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/**
 * @since 0.1.0
 */
class PluginStorkmdmFleet extends CommonDBTM implements PluginStorkmdmNotifiable {

   /**
    * @var string $rightname name of the right in DB
    */
   static $rightname = 'storkmdm:fleet';

   /**
    * @var bool $dohistory maintain history
    */
   public $dohistory = true;

   /**
    * @var bool $usenotepad enable notepad for the itemtype (GLPi < 0.85)
    */
   protected $usenotepad               = true;

   /**
    * @var bool $usenotepad enable notepad for the itemtype (GLPi >=0.85)
    */
   protected $usenotepadRights         = true;

   static $types = array(
         'Phone'
   );

   /**
    * Localized name of the type
    * @param $nb integer number of item in the type (default 0)
    */
   public static function getTypeName($nb = 0) {
      global $LANG;
      return _n('Fleet', 'Fleets', $nb, "storkmdm");
   }

   /**
    * {@inheritDoc}
    * @see CommonGLPI::defineTabs()
    */
   public function defineTabs($options = array()) {
      $tab = array();
      $this->addDefaultFormTab($tab);
      $this->addStandardTab('PluginStorkmdmAgent_Fleet', $tab, $options);
      $this->addStandardTab('Notepad', $tab, $options);
      $this->addStandardTab('Log', $tab, $options);

      return $tab;
   }

   /**
    * Show form for edition
    */
   public function showForm($ID, $options = array()) {
      global $CFG_GLPI, $DB;

      $this->initForm($ID, $options);
      $this->showFormHeader();

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __s('Name') . (isset($options['withtemplate']) && $options['withtemplate'] ? "*" : "") . "</td>";
      echo "<td>";
      $objectName = autoName($this->fields["name"], "name", (isset($options['withtemplate']) && $options['withtemplate'] == 2), $this->getType(), - 1);
      Html::autocompletionTextField($this, 'name', array(
            'value' => $objectName
      ));
      echo "</td>";

      $this->showFormButtons($options);

   }

   /**
    * {@inheritDoc}
    * @see CommonDBTM::prepareInputForAdd()
    */
   public function prepareInputForAdd($input) {
      if (!isset($input['is_default'])) {
         $input['is_default'] = '0';
      }
      return $input;
   }

   /**
    * {@inheritDoc}
    * @see CommonDBTM::prepareInputForUpdate()
    */
   public function prepareInputForUpdate($input) {
      unset($input['is_default']);

      return $input;
   }

   /**
    * Actions done before the DELETE of the item in the database /
    * Maybe used to add another check for deletion
    * @return bool : true if item need to be deleted else false
    */
   public function pre_deleteItem() {
      global $DB;

      // check if fleet being deleted is the default one
      if ($this->fields['is_default'] == '1') {

         $config = Config::getConfigurationValues('storkmdm', array('service_profiles_id'));
         if ( !Entity::canPurge() && $_SESSION['glpiactiveprofile']['id'] != $config['service_profiles_id']) {
            Session::addMessageAfterRedirect(__('Cannot delete the default fleet', 'storkmdm'));
            return false;
         }
      }

      // move agents in the fleet into the default one
      $fleetId = $this->getID();
      $agent = new PluginStorkmdmAgent();
      $entityId = $this->fields['entities_id'];
      $defaultFleet = self::getDefaultFleet($entityId);
      $agents = $this->getAgents();
      if ($defaultFleet === null && count($agents) > 0) {
         // No default fleet
         // TODO : Create it again ?
         Session::addMessageAfterRedirect(__('No default fleet found to move devices', 'storkmdm'));
         return false;
      }

      foreach ($agents as $agent) {
         if (!$agent->update([
               'id'                          => $agent->getID(),
               'plugin_storkmdm_fleets_id'   => $defaultFleet->getID()
         ])) {
            Session::addMessageAfterRedirect(__('Could not move all devices to the not managed fleet', 'storkmdm'));
            return false;
         }
      }

      //Delete policies on the fleet
      $fleetId = $this->getID();
      $fleet_Policy = new PluginStorkmdmFleet_Policy();
      $rows = $fleet_Policy->find("`plugin_storkmdm_fleets_id` = '$fleetId'");
      foreach ($rows as $row) {
         $decodedValue = json_decode($row['value'], JSON_OBJECT_AS_ARRAY);
         if (isset($decodedValue['remove_on_delete']) && $decodedValue['remove_on_delete'] != '0') {
            $decodedValue['remove_on_delete'] = '0';
            $row['value'] = $decodedValue;
            $fleet_Policy->update($row);
         }
      }
      if (!$fleet_Policy->deleteByCriteria(array('plugin_storkmdm_fleets_id' => $fleetId), true)) {
         Session::addMessageAfterRedirect(__('Could not delete policies on the fleet','storkmdm'));
         return false;
      }

      $mqttQueue = new PluginStorkmdmMqttupdatequeue();
      if (!$mqttQueue->deleteByCriteria(array('plugin_storkmdm_fleets_id' => $fleetId))) {
         Session::addMessageAfterRedirect(__('Could not delete message queue on the fleet','storkmdm'));
         // Do not fail yet. We need a CRON purge feature on this itemtype
         //return false;
      }

      return true;
   }

   /**
    * {@inheritDoc}
    * @see CommonDBTM::getSearchOptions()
    */
   public function getSearchOptions() {
      global $CFG_GLPI;

      $tab = array();
      $tab['common']                 = __s('Fleet', "storkmdm");

      $i = 1;
      $tab[$i]['table']               = self::getTable();
      $tab[$i]['field']               = 'name';
      $tab[$i]['name']                = __('Name');
      $tab[$i]['datatype']            = 'itemlink';
      $tab[$i]['massiveaction']       = false;

      $i++;
      $tab[$i]['table']               = self::getTable();
      $tab[$i]['field']               = 'id';
      $tab[$i]['name']                = __('ID');
      $tab[$i]['massiveaction']       = false;
      $tab[$i]['datatype']            = 'number';

      $i++;
      $tab[$i]['table']               = PluginStorkmdmFleet_Policy::getTable();
      $tab[$i]['field']               = 'items_id';
      $tab[$i]['name']                = _n('Associated element', 'Associated elements', Session::getPluralNumber());
      $tab[$i]['datatype']            = 'specific';
      $tab[$i]['comments']            = true;
      $tab[$i]['nosort']              = true;
      $tab[$i]['nosearch']            = true;
      $tab[$i]['additionalfields']    = array('itemtype');
      $tab[$i]['joinparams']          = array('jointype'   => 'child');
      $tab[$i]['forcegroupby']        = true;
      $tab[$i]['massiveaction']       = false;

      $i++;
      $tab[$i]['table']               = PluginStorkmdmFleet_Policy::getTable();
      $tab[$i]['field']               = 'itemtype';
      $tab[$i]['name']                = _n('Associated item type', 'Associated item types', Session::getPluralNumber());
      $tab[$i]['datatype']            = 'itemtypename';
      $tab[$i]['itemtype_list']       = 'fleet_types';
      $tab[$i]['nosort']              = true;
      $tab[$i]['additionalfields']    = array('itemtype');
      $tab[$i]['joinparams']          = array('jointype'   => 'child');
      $tab[$i]['forcegroupby']        = true;
      $tab[$i]['massiveaction']       = false;

      $i++;
      $tab[$i]['table']           = self::getTable();
      $tab[$i]['field']           = 'is_default';
      $tab[$i]['name']            = __('Not managed', 'storkmdm');
      $tab[$i]['datatype']        = 'bool';
      $tab[$i]['massiveaction']   = false;

      return $tab;
   }

   /**
    *
    * {@inheritDoc}
    * @see PluginStorkmdmNotifiable::getTopic()
    */
   public function getTopic() {
      if (!isset($this->fields['id']) ) {
         return null;
      }

      return '/' . $this->fields['entities_id'] . '/fleet/' . $this->fields['id'];
   }

   /**
    * Actions done after the ADD of the item in the database
    * @return nothing
    */
   public function post_addItem() {
      // Generate default policies for groups of policies
      $fleet_policy = new PluginStorkmdmFleet_Policy();
      $fleet_policy->publishPolicies($this, array('camera', 'connectivity', 'encryption', 'policies'));
   }

   /**
    *
    * {@inheritDoc}
    * @see CommonDBTM::post_deleteItem()
    */
   public function post_deleteItem() {
      // unlink agents
      $this->post_purgeItem();
   }

   /**
    *
    * {@inheritDoc}
    * @see CommonDBTM::post_purgeItem()
    */
   public function post_purgeItem() {
      global $DB;

      // now the fleet is empty, delete MQTT topcis
      $table_policy = PluginStorkmdmPolicy::getTable();
      $query = "SELECT DISTINCT `group` FROM `$table_policy`";
      $result = $DB->query($query);
      if ($result) {
         $groups = array();
         while ($row = $DB->fetch_assoc($result)) {
            $groups[] = $row['group'];
         }
      }
      PluginStorkmdmFleet_Policy::cleanupPolicies($this, $groups);
   }

   /**
    * {@inheritDoc}
    * @see CommonDBTM::cleanDBonPurge()
    */
   public function cleanDBonPurge() {
      global $DB;

      // Unsuscribe all agents from the fleet
      $fleetId = $this->getID();
      $query = "SELECT `id`
      FROM `glpi_plugin_storkmdm_agents`
      WHERE `glpi_plugin_storkmdm_agents`.`plugin_storkmdm_fleets_id` = '$fleetId'";

      if ($result = $DB->query($query)) {
         while ($row = $DB->fetch_assoc($result)) {
            $agent = new PluginStorkmdmAgent();
            if ($agent->getFromDB($row['id'])) {
               $agent->unsubscribe();
            }
         }
      }

      // Force deletion regardless a file or application removal policy should take place
      $fleet_policyTable = getTableForItemType('PluginStorkmdmFleet_Policy');
      $itemId = $this->getID();
      $query = "DELETE FROM `$fleet_policyTable` WHERE `plugin_storkmdm_fleets_id`='$itemId'";
      $DB->query($query);
   }

   /**
    * {@inheritDoc}
    * @see PluginStorkmdmNotifiable::getAgents()
    */
   public function getAgents() {
      $id = $this->getID();
      if (! ($id > 0) ) {
         return array();
      }
      $agents = array();
      $agent = new PluginStorkmdmAgent();
      $rows = $agent->find("`plugin_storkmdm_fleets_id`='$id'");

      foreach ($rows as $agentId => $row) {
         $agent = new PluginStorkmdmAgent();
         if ($agent->getFromDB($agentId)) {
            $agents[] = $agent;
         }
      }

      return $agents;
   }

   /**
    * {@inheritDoc}
    * @see PluginStorkmdmNotifiable::getFleet()
    */
   public function getFleet() {
      if ($this->isNewItem()) {
         return null;
      }

      return $this;
   }

   /**
    * {@inheritDoc}
    * @see PluginStorkmdmNotifiable::getPackages()
    */
   public function getPackages() {
      $packages = array();

      $fleetId = $this->getID();
      if ($fleetId > 0) {
         $fleet_policy = new PluginStorkmdmFleet_Policy();
         $rows = $fleet_policy->find("`plugin_storkmdm_fleets_id`='$fleetId' AND `itemtype`='PluginStorkmdmPackage'");
         foreach ($rows as $id => $row) {
            $package = new PluginStorkmdmPackage();
            $package->getFromDB($row['plugin_storkmdm_packages_id']);
            $packages[] = $package;
         }
      }

      return $packages;
   }

   /**
    * {@inheritDoc}
    * @see PluginStorkmdmNotifiable::getFiles()
    */
   public function getFiles() {
      $files = array();

      $fleetId = $this->getID();
      if ($fleetId > 0) {
         $fleet_policy = new PluginStorkmdmFleet_Policy();
         $rows = $fleet_policy->find("`plugin_storkmdm_fleets_id`='$fleetId' AND `itemtype`='PluginStorkmdmFile'");
         foreach ($rows as $id => $row) {
            $file = new PluginStorkmdmPackage();
            $file->getFromDB($row['plugin_storkmdm_packages_id']);
            $files[] = $file;
         }
      }

      return $files;
   }

   /**
    * Gets the default fleet for an entity
    * @param string $entityId ID of the entoty to search in
    * @return PluginStorkmdmFleet|null
    */
   public static function getDefaultFleet($entityId = '') {
      if ($entityId == '') {
         $entityId = $_SESSION['glpiactive_entity'];
      }

      $defaultFleet = new PluginStorkmdmFleet();
      if (!$defaultFleet->getFromDBByQuery(
            "WHERE `is_default`='1' AND `entities_id`='$entityId'"
            )) {
         return null;
      }
      return $defaultFleet;
   }

   /**
    *
    * {@inheritDoc}
    * @see PluginStorkmdmNotifiable::notify()
    */
   public function notify($topic, $mqttMessage, $qos = 0, $retain = 0) {
      $mqttClient = PluginStorkmdmMqttclient::getInstance();
      $mqttClient->publish($topic, $mqttMessage, $qos, $retain);
   }
}
