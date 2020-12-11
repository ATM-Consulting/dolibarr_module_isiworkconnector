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
 * 	\file		admin/isiworkconnector.php
 * 	\ingroup	isiworkconnector
 * 	\brief		This file is an example module setup page
 * 				Put some comments here
 */
// Dolibarr environment
$res = @include '../../main.inc.php'; // From htdocs directory
if (! $res) {
    $res = @include '../../../main.inc.php'; // From "custom" directory
}

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once '../lib/isiworkconnector.lib.php';
dol_include_once('abricot/includes/lib/admin.lib.php');

// Translations
$langs->loadLangs(array('isiworkconnector@isiworkconnector', 'admin', 'other'));

// Access control
if (! $user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'alpha');

/*
 * Actions
 */
if (preg_match('/set_(.*)/', $action, $reg))
{
	$code=$reg[1];
	if (dolibarr_set_const($db, $code, GETPOST($code), 'chaine', 0, '', $conf->entity) > 0)
	{
		header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
	else
	{
		dol_print_error($db);
	}
}

if (preg_match('/del_(.*)/', $action, $reg))
{
	$code=$reg[1];
	if (dolibarr_del_const($db, $code, 0) > 0)
	{
		Header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
	else
	{
		dol_print_error($db);
	}
}

/*
 * View
 */
$page_name = "isiworkconnectorSetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'
    . $langs->trans("BackToModuleList") . '</a>';
print load_fiche_titre($langs->trans($page_name), $linkback);

// Configuration header
$head = isiworkconnectorAdminPrepareHead();
dol_fiche_head(
    $head,
    'settings',
    $langs->trans("Module104654Name"),
    -1,
    "isiworkconnector@isiworkconnector"
);

// Setup page goes here
$form=new Form($db);
$var=false;
print '<table class="noborder" width="100%">';


if(!function_exists('setup_print_title')){
    print '<div class="error" >'.$langs->trans('AbricotNeedUpdate').' : <a href="http://wiki.atm-consulting.fr/index.php/Accueil#Abricot" target="_blank"><i class="fa fa-info"></i> Wiki</a></div>';
    exit;
}

setup_print_title("Parameters");

// Example with a yes / no select
setup_print_on_off('CONSTNAME', $langs->trans('ParamLabel'), 'ParamDesc');

// Example with imput
setup_print_input_form_part('CONSTNAME', $langs->trans('ParamLabel'));

// Example with color
setup_print_input_form_part('CONSTNAME', $langs->trans('ParamLabel'), 'ParamDesc', array('type'=>'color'), 'input', 'ParamHelp');

// Example with placeholder
//setup_print_input_form_part('CONSTNAME',$langs->trans('ParamLabel'),'ParamDesc',array('placeholder'=>'http://'),'input','ParamHelp');

// Example with textarea
//setup_print_input_form_part('CONSTNAME',$langs->trans('ParamLabel'),'ParamDesc',array(),'textarea');

print '</table>';


//-------------------------------------------------------------------------------
print '<br />';
print load_fiche_titre('ZEENDOC', '', '');
print '<table class="noborder" width="100%">';
setup_print_title($langs->trans('ZeenDocSendParameters'));

$params = ['type'=>'text', 'placeholder' => 'login'];
setup_print_input_form_part('FINANCEMENT_URL_ZEENDOC_LOGIN', $langs->trans('FINANCEMENT_URL_ZEENDOC_LOGIN'), '', $params, 'input');

$params = ['type'=>'password', 'placeholder' => '*******'];
setup_print_input_form_part('FINANCEMENT_URL_ZEENDOC_PASSWORD', $langs->trans('FINANCEMENT_URL_ZEENDOC_PASSWORD'), '', $params, 'input');

$params = ['type'=>'text', 'placeholder' => ''];
setup_print_input_form_part('FINANCEMENT_URL_ZEENDOC_URLCLIENT', $langs->trans('FINANCEMENT_URL_ZEENDOC_URLCLIENT'), '', $params, 'input');

$params = ['type'=>'url', 'placeholder' => 'https://armoires.Zeendoc.com/_WebServices/Upload/0_3'];
setup_print_input_form_part('FINANCEMENT_URL_ZEENDOC_SERVICE_URL_BASE', $langs->trans('FINANCEMENT_URL_ZEENDOC_SERVICE_URL_BASE'), '', $params, 'input');

$params = ['type'=>'text', 'placeholder' => "nom du classeur"];
setup_print_input_form_part('FINANCEMENT_URL_ZEENDOC_CLASSEUR', $langs->trans('FINANCEMENT_URL_ZEENDOC_CLASSEUR'), '', $params, 'input');

$params = ['type'=>'text', 'placeholder' => "Source de documents"];
setup_print_input_form_part('FINANCEMENT_URL_ZEENDOC_IDSOURCE', $langs->trans('FINANCEMENT_URL_ZEENDOC_IDSOURCE'), '', $params, 'input');

print '</table>';

dol_fiche_end(-1);

llxFooter();

$db->close();
