<?php

use Tygh\Registry;
use Tygh\Tygh;

defined('BOOTSTRAP') or die('Access denied');
$auth = & Tygh::$app['session']['auth'];

if ($mode == 'departments'){
    Tygh::$app['session']['continue_url'] = "departments.departments";

    $params = $_REQUEST;

    if ($items_per_page = fn_change_session_param(Tygh::$app['session']['search_params'], $_REQUEST, 'items_per_page')) {
        $params['items_per_page'] = $items_per_page;
    }
    if ($sort_by = fn_change_session_param(Tygh::$app['session']['search_params'], $_REQUEST, 'sort_by')) {
        $params['sort_by'] = $sort_by;
    }
    if ($sort_order = fn_change_session_param(Tygh::$app['session']['search_params'], $_REQUEST, 'sort_order')) {
        $params['sort_order'] = $sort_order;
    }

    list($departments, $search) = fn_get_departments($params, Registry::get('settings.Appearance.products_per_page'), CART_LANGUAGE);
    if (isset($search['page']) && ($search['page'] > 1) && empty($departments)) {
        return array(CONTROLLER_STATUS_NO_PAGE);
    }

    Tygh::$app['view']->assign([
        'departments' => $departments,
        'search' => $search,
    ]);
    fn_add_breadcrumb(__("departments"));
} elseif ($mode === 'department') 
{
    $department_data = [];
    $department_id = !empty($_REQUEST['department_id']) ? $_REQUEST['department_id'] : 0;
    $department_data = fn_get_department_data($department_id, CART_LANGUAGE);
    // fn_print_die($department_data);

    if (empty($department_data)) {
        return [CONTROLLER_STATUS_NO_PAGE];
    }
    Tygh::$app['view']->assign('department_data', $department_data);
    fn_add_breadcrumb(__("departments"), $department_data['department']);

    $params = $_REQUEST;
    $params['extend'] = ['description'];
    $params['item_ids'] = !empty($department_data['user_ids']) ? implode(',', $department_data['user_ids']) : -1;

    if ($items_per_page = fn_change_session_param(Tygh::$app['session']['search_params'], $_REQUEST, 'items_per_page')) {
        $params['items_per_page'] = $items_per_page;
    }
    if ($sort_by = fn_change_session_param(Tygh::$app['session']['search_params'], $_REQUEST, 'sort_by')) {
        $params['sort_by'] = $sort_by;
    }
    if ($sort_order = fn_change_session_param(Tygh::$app['session']['search_params'], $_REQUEST, 'sort_order')) {
        $params['sort_order'] = $sort_order;
    }

    list($products, $search) = fn_get_products($params, Registry::get('settings.Appearance.products_per_page'));

    if (isset($search['page']) && ($search['page'] > 1) && empty($departments) && (!defined('AJAX_REQUEST'))) {
        return [CONTROLLER_STATUS_NO_PAGE];
    }

    fn_filters_handle_search_result($params, $products, $search);
    fn_gather_additional_products_data($products, [
        'get_icon'      => true,
        'get_detailed'  => true,
        'get_options'   => true,
        'get_discounts' => true,
        'get_features'  => false
    ]);

    $selected_layout = fn_get_products_layout($_REQUEST);
    Tygh::$app['view']->assign([
        'products' => $products,
        'search' => $search,
        'selected_layout' => $selected_layout
    ]);
}