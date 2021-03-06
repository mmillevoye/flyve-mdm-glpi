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

if (! defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/**
 * @since 0.1.31
 */
interface PluginStorkmdmNotifiable {

   public function getTopic();

   /**
    * get the agents related to the notifiable
    * @return array the final PluginStorkmdmAgents to be notified
    */
   public function getAgents();

   /**
    * get the fleet attached to the notifiable
    * @return PluginStorkmdmFleet the fleet associated to the notifiable
    */
   public function  getFleet();

   /**
    * get the applications related to the notifiable
    * @return array of PluginStorkmdmPackage
    */
   public function getPackages();

   /**
    * get the files related to the notifiable
    * @return array of PluginStorkmdmFile
    */
   public function getFiles();

   /**
    * Send a MQTT message
    * @param string $topic
    * @param string $mqttMessage
    * @param number $qos
    * @param number $retain
    */
   public function notify($topic, $mqttMessage, $qos = 0, $retain = 0);
}
