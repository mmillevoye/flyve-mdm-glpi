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

class PluginStorkmdmInvitationlog extends CommonDBTM {

   /**
    * @var string $rightname name of the right in DB
    */
   public static $rightname            = 'storkmdm:invitationLog';

   /**
    * Localized name of the type
    * @param $nb  integer  number of item in the type (default 0)
    */
   public static function getTypeName($nb=0) {
      global $LANG;
      return _n('Invitation log', 'Invitation logs', $nb, "storkmdm");
   }

   /**
    * @since version 0.1.0
    * @see commonDBTM::getRights()
    */
   public function getRights($interface = 'central') {
      $rights = parent::getRights();
      /// For additional righrs if needed
      //$rights[self::RIGHTS] = self::getTypeName();

      return $rights;
   }

   /**
    * {@inheritDoc}
    * @see CommonDBTM::getSearchOptions()
    */
   public function getSearchOptions() {
      global $CFG_GLPI;

      $tab = array();
      $tab['common']             = __s('Invitation log', "storkmdm");

      $i = 2;
      $tab[$i]['table']           = self::getTable();
      $tab[$i]['field']           = 'id';
      $tab[$i]['name']            = __('ID');
      $tab[$i]['massiveaction']   = false;
      $tab[$i]['datatype']        = 'number';

      $i++;
      $tab[$i]['table']           = self::getTable();
      $tab[$i]['field']           = 'date_creation';
      $tab[$i]['name']            = __('date');
      $tab[$i]['massiveaction']   = false;
      $tab[$i]['datatype']        = 'datetime';

      $i++;
      $tab[$i]['table']           = PluginStorkmdmInvitation::getTable();
      $tab[$i]['field']           = 'id';
      $tab[$i]['name']            = __('ID');
      $tab[$i]['massiveaction']   = false;
      $tab[$i]['datatype']        = 'dropdown';

      $i++;
      $tab[$i]['table']           = self::getTable();
      $tab[$i]['field']           = 'event';
      $tab[$i]['name']            = __('event', 'storkmdm');
      $tab[$i]['massiveaction']   = false;
      $tab[$i]['datatype']        = 'string';

      return $tab;
   }

}