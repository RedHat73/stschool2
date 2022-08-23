<?php

use Tygh\Registry;
use Tygh\Tygh;

defined('BOOTSTRAP') or die('Access denied');

$auth = & Tygh::$app['session']['auth'];
$_REQUEST['department_id'] = isset($_REQUEST['department_id']) ? $_REQUEST['department_id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    fn_trusted_vars(
        'departments', 
        'department_data',
    );
    $suffix = '';

    if ($mode == 'update') {
        $department_id = isset ($_REQUEST ['department_id']) ? $_REQUEST ['department_id'] : null;
        $data = isset ($_REQUEST['department_data']) ? $_REQUEST['department_data'] : [];
        $department_id = fn_update_department($data, $department_id, $lang_code = CART_LANGUAGE);

        if (
            $mode === 'update'
            && isset($_REQUEST['department_ids'])
            && is_array($_REQUEST['department_ids'])
            && isset($_REQUEST['status'])
        ) {
            $status_to = (string) $_REQUEST['status'];
            foreach ($_REQUEST['department_ids'] as $department_id) {
                fn_tools_update_status([
                    'table'             => 'departments',
                    'status'            => $status_to,
                    'id_name'           => 'department_id',
                    'id'                => $department_id,
                    'show_error_notice' => false
                ]);
            }
        }
        if (isset($department_id)) {
            $suffix = ".update?department_id = {'$department_id'}";
            return [
                CONTROLLER_STATUS_REDIRECT,
                'departments.update',
            ];
        }
    } elseif ($mode == 'delete') {
        if (isset($_REQUEST['department_id'])) {
            fn_delete_department_by_id($_REQUEST['department_id']);
            return [
                CONTROLLER_STATUS_REDIRECT,
                'departments.manage',
            ];
        }
    } elseif ($mode == 'm_delete') {
        foreach ($_REQUEST['department_ids'] as $v) 
        {
            fn_delete_department_by_id($v);
        }
        return array(CONTROLLER_STATUS_REDIRECT, 'departments.manage');
    }
}

if ($mode === 'update') {
    $department_id = isset($_REQUEST['department_id']) ? $_REQUEST['department_id'] : null;
    $department_data = fn_get_department_data($department_id, CART_LANGUAGE);

    if($department_id = empty($_REQUEST['department_id'])) {
        return [
            CONTROLLER_STATUS_REDIRECT,
            'departments.manage',
        ];
    }
    else $_REQUEST['department_id'];

    if (empty($department_data && $mode === 'update')) {
        return [CONTROLLER_STATUS_NO_PAGE];
    }
    Tygh::$app['view']->assign([
        'department_data' => $department_data,
        'supervisor' => isset($department_data ['user_id']) ? fn_get_user_short_info($department_data ['user_id']) : [],
    ]);
} elseif ($mode === 'manage' || $mode == 'picker') {
    list($departments, $search) = fn_get_departments($_REQUEST, Registry::get('settings.Appearance.admin_elements_per_page'), CART_LANGUAGE);
    Tygh::$app['view']->assign([
        'departments' => $departments,
        'search' => $search,
    ]);
}
