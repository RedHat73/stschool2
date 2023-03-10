<?php

use Tygh\Languages\Languages;

function fn_get_department_data($department_id = null, $lang_code = CART_LANGUAGE)
{
    $departments = [];
    if (isset($department_id)) {
        list ($departments) = fn_get_departments([
            'department_id' => $department_id
        ], 1, $lang_code);

        if(isset($departments)) {
            $departments = reset($departments);
            $departments['user_ids'] = fn_department_get_links ($departments['department_id']);
        }
    }
    return $departments;
}

function fn_get_departments($params = [], $items_per_page = 10, $lang_code = CART_LANGUAGE)
{
    $default_params = [
        'page' => 1,
        'items_per_page' => $items_per_page
    ];

    $params = array_merge($default_params, $params);

    if (AREA == 'C') {
        $params['status'] = 'A';
    }

    $sortings = [
        'timestamp' => '?:departments.timestamp',
        'name' => '?:department_descriptions.department',
        'status' => '?:departments.status',
    ];

    $condition = '';
    $limit = '';
    $join = '';

    if (isset($params['limit'])) {
        $limit = db_quote(' LIMIT 0, ?i', $params['limit']);
    }

    $sorting = db_sort($params, $sortings, 'name', 'asc');

    if (!empty($params['item_ids'])) {
        $condition .= db_quote(' AND ?:departments.department_id IN (?n)', explode(',', $params['item_ids']));
    }

    if (!empty($params['name'])) {
        $condition .= db_quote(' AND ?:department_descriptions.department LIKE ?l', '%' . trim($params['name']) . '%');
    }

    if (!empty($params['department_id'])) {
        $condition .= db_quote(' AND ?:departments.department_id = ?i', $params['department_id']);
    }

    if (!empty($params['timestamp'])) {
        $condition .= db_quote(' AND ?:departments.timestamp = ?i', $params['timestamp']);
    }

    if (!empty($params['status'])) {
        $condition .= db_quote(' AND ?:departments.status = ?s', $params['status']);
    }

    $fields = [
        '?:departments.*',
        '?:department_descriptions.department',
        '?:department_descriptions.description',
        '?:department_images.department_image_id',
    ];

    $join .= db_quote(' LEFT JOIN ?:department_descriptions ON ?:department_descriptions.department_id = ?:departments.department_id AND ?:department_descriptions.lang_code = ?s', $lang_code);
    $join .= db_quote(' LEFT JOIN ?:department_images ON ?:department_images.department_id = ?:departments.department_id AND ?:department_images.lang_code = ?s', $lang_code);

    if (!empty($params['items_per_page'])) {
        $params['total_items'] = db_get_field("SELECT COUNT(*) FROM ?:departments $join WHERE 1 $condition");
        $limit = db_paginate($params['page'], $params['items_per_page'], $params['total_items']);
    }

    $departments = db_get_hash_array(
        "SELECT ?p FROM ?:departments " .
        $join .
        "WHERE 1 ?p ?p ?p",
        'department_id', implode(', ', $fields), $condition, $sorting, $limit
    );

    if (!empty($params['item_ids'])) {
        $departments = fn_sort_by_ids($departments, explode(',', $params['item_ids']), 'department_id');
    }
    
    $department_image_ids = array_keys($departments);
    $images = fn_get_image_pairs($department_image_ids, 'department', 'M', true, false, $lang_code);

    foreach ($departments as $department_id => $department) {
        $departments[$department_id]['main_pair'] = !empty($images[$department_id]) ? reset($images[$department_id]) : array();
    }

    return [
        $departments,
        $params,
    ];
}

function fn_update_department($data, $department_id, $lang_code = CART_LANGUAGE)
{
    if (!empty($data['timestamp'])) {
        $data['timestamp'] = fn_parse_date($data['timestamp']);
    }

    if (!empty($department_id)) {
        $department_image_id = fn_get_department_image_id($department_id);

        db_query("UPDATE ?:departments SET ?u WHERE department_id = ?i", $data, $department_id);
        db_query("UPDATE ?:department_descriptions SET ?u WHERE department_id = ?i AND lang_code = ?s", $data, $department_id, $lang_code);
        db_query("UPDATE ?:department_images SET ?u WHERE department_id = ?i", $data, $department_id);

        $department_image_id = fn_get_department_image_id($department_id);
        $department_image_exist = isset($department_image_id);
        $image_is_update = fn_departments_need_image_update();
        $pair_data = fn_attach_image_pairs('department', 'department', $department_image_id, $lang_code);

        if (!$image_is_update && $department_image_exist) {
            fn_delete_image_pairs($department_image_id, 'department');
            db_query("DELETE FROM ?:department_images WHERE department_id = ?i AND lang_code = ?s", $department_id, $lang_code);
            $department_image_exist = false;
        }

        if ($image_is_update && !$department_image_exist) {
            $department_image_id = db_query("INSERT INTO ?:department_images (department_id, lang_code) VALUE(?i, ?s)", $department_id, $lang_code);
        }
    }
    else {
        $department_id = $data['department_id'] = db_query("REPLACE INTO ?:departments ?e", $data);
        foreach (Languages::getAll() as $data['lang_code'] => $v) {
            db_query("REPLACE INTO ?:department_descriptions ?e", $data);
        }
        if (isset($department_id)) {
            $pair_data = fn_attach_image_pairs('department', 'department', $department_id, $lang_code);

            if (fn_departments_need_image_update()) {
                $department_image_id = db_get_next_auto_increment_id('department_images');

                if (isset($pair_data)) {
                    $data_department_image = [
                        'department_image_id' => $department_image_id,
                        'department_id'       => $department_id,
                    ];
                    fn_departments_image_all_links($department_id, $pair_data);
                }
            }
        }
    }

    $user_ids = isset($data['user_ids']) ? $data ['user_ids'] : [];
    fn_department_delete_links($department_id);
    fn_department_add_links($department_id, $user_ids);

    return $department_id;
}

function fn_department_delete_links($department_id)
{
    db_query("DELETE FROM ?:department_links WHERE department_id = ?i", $department_id);

}

function fn_department_add_links($department_id, $user_ids)
{
    if (!empty($user_ids['0'])) {
        $user_ids = explode( ',', $user_ids['0']);
        foreach ($user_ids as $user_id) {
            db_query("REPLACE INTO ?:department_links ?e", [
                'employee_id' => $user_id,
                'department_id' => $department_id,
            ]);
        }
    }
}

function fn_department_get_links($department_id)
{
    return isset($department_id) ? db_get_fields('SELECT employee_id from ?:department_links WHERE department_id = ?i', $department_id) : [];
}

function fn_departments_need_image_update()
{
    if (!empty($_REQUEST['file_department_image_icon']) && is_array($_REQUEST['file_department_image_icon'])) {
        $image_department = reset($_REQUEST['file_department_image_icon']);

        if ($image_department == 'department') {
            return false;
        }
    }

    return true;
}

function fn_get_department_image_id($department_id)
{
    return db_get_field("SELECT department_image_id FROM ?:department_images WHERE department_id = ?i", $department_id);
}

function fn_departments_image_all_links($department_id, $pair_data)
{
    if (isset($pair_data)) {
        $pair_id = reset($pair_data);
        $_department_image_id = db_query("INSERT INTO ?:department_images (department_id) VALUE (?i)", $department_id);
        fn_add_image_link($_department_image_id, $pair_id);     
    }
}

function fn_delete_department_by_id($department_id)
{
    if (!empty($department_id)) {
        db_query("DELETE FROM ?:departments WHERE department_id = ?i", $department_id);
        db_query("DELETE FROM ?:department_descriptions WHERE department_id = ?i", $department_id);
        db_query("DELETE FROM ?:department_images WHERE department_id = ?i", $department_id);
    }
}
