<?php
/* Copyright (C) 2019 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *      \file       admin/isiworkconnector_extrafields.php
 *		\ingroup    isiworkconnector
 *		\brief      Page to setup extra fields of isiworkconnector
 */

$res = @include '../../main.inc.php'; // From htdocs directory
if (! $res) {
    $res = @include '../../../main.inc.php'; // From "custom" directory
}


/*
 * Config of extrafield page for isiworkconnector
 */
require_once '../lib/isiworkconnector.lib.php';
require_once '../class/ZennDoc.Connector.class.php';
$langs->loadLangs(array('isiworkconnector@isiworkconnector', 'admin', 'other'));

$isiworkconnector = new isiworkconnector($db);
$elementtype=$isiworkconnector->table_element;  //Must be the $table_element of the class that manage extrafield

// Page title and texts elements
$textobject=$langs->transnoentitiesnoconv('isiworkconnector');
$help_url='EN:Help isiworkconnector|FR:Aide isiworkconnector';
$pageTitle = $langs->trans('isiworkconnectorExtrafieldPage');

// Configuration header
$head = isiworkconnectorAdminPrepareHead();



/*
 *  Include of extrafield page
 */

require_once dol_buildpath('abricot/tpl/extrafields_setup.tpl.php'); // use this kind of call for variables scope
