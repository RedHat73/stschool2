<?php

use Tygh\Languages\Languages;

/**
 * Get specific department data
 *
 * @param int   $department_id Department ID
 * @param str   $lang_code     Language code
 *
 * @return array Departments data
 */
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

/**
 * Gets departments list by search params
 *
 * @param array  $params         Department search params
 * @param string $lang_code      2 letters language code
 * @param int    $items_per_page Items per page
 *
 * @return array Departments list and Search params
 */
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

    foreach ($department_image_ids as $department_image_id => $department_id) {
        if (!empty(fn_get_department_id_image($department_id))){
                $department_ids [] = fn_get_department_id_image($department_id);
        }
    }

    $images = fn_get_image_pairs($department_ids, 'department', 'M', true, false, $lang_code);

    foreach ($departments as $department_id => $department) {
        $departments[$department_id]['main_pair'] = !empty($images[$department_id]) ? reset($images[$department_id]) : [];
    }

    return [
        $departments,
        $params,
    ];
}

/**
 * Get updated department data
 *
 * @param array $params        Department data params
 * @param int   $department_id Department ID
 * @param str   $lang_code     Language code
 *
 * @return int  $department_id Department ID
 */
function fn_update_department($data, $department_id, $lang_code = CART_LANGUAGE)
{
    if (!empty($data['timestamp'])) {
        $data['timestamp'] = fn_parse_date($data['timestamp']);
    }

    if (!empty($department_id)) {

        db_query("UPDATE ?:departments SET ?u WHERE department_id = ?i", $data, $department_id);
        db_query("UPDATE ?:department_descriptions SET ?u WHERE department_id = ?i AND lang_code = ?s", $data, $department_id, $lang_code);
        db_query("UPDATE ?:department_images SET ?u WHERE department_id = ?i", $data, $department_id);

        $department_id_image = fn_get_department_id_image($department_id);
        $department_image_exist = isset($department_id_image);
        $image_is_update = fn_departments_need_image_update($department_id);
        $pair_data = fn_attach_image_pairs('department', 'department', $department_id_image, $lang_code);

        if (!$image_is_update && !$pair_data) {
            fn_department_image_delete($department_id);
        }

        if ($image_is_update && !$pair_data) {
            fn_department_image_delete($department_id);
        }
        
        if ($image_is_update && !$department_image_exist) {
            $department_id_image = db_query("INSERT INTO ?:department_images (department_id) VALUE(?i)", $department_id);
        }
        
        if ($department_id && !$department_id_image && $pair_data) {
        fn_departments_image_all_links($department_id, $pair_data);
        }
    }
    else {
        $department_id = $data['department_id'] = db_query("REPLACE INTO ?:departments ?e", $data);
        foreach (Languages::getAll() as $data['lang_code'] => $v) {
            db_query("REPLACE INTO ?:department_descriptions ?e", $data);
        }
        if (!empty($department_id)) {
            if (fn_departments_need_image_update($department_id)) {
                $pair_data = fn_attach_image_pairs('department', 'department', $department_id, $lang_code);
                $department_image_id = $department_id;
                
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

/**
 * Removes the association of a department with the selected users
 *
 * @param int $department_id Department identificator
 */
function fn_department_delete_links($department_id)
{
    db_query("DELETE FROM ?:department_links WHERE department_id = ?i", $department_id);
}

/**
 * Adds a department link to the selected users
 *
 * @param int $department_id Department identificator
 */
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

/**
 * Gets data about the connection of the department with the selected users
 *
 * @param int $department_id Department identificator
 */
function fn_department_get_links($department_id)
{
    return isset($department_id) ? db_get_fields('SELECT employee_id from ?:department_links WHERE department_id = ?i', $department_id) : [];
}

/**
 * Checks of request for need to update the department image.
 *
 * @return bool
 */
function fn_departments_need_image_update()
{ 
        if(empty($_REQUEST['department_image_data']['0']['pair_id']) && is_array($_REQUEST['file_department_image_icon'])){ 
            $image_department_load = reset($_REQUEST['file_department_image_icon']) === 'department' ? false : true;
            return $image_department_load;
        }
    return true;
}


/**
 * Deletes the image of the department
 *
 * @param int $department_id Department identificator
 */
function fn_department_image_delete($department_id)
{       
        $department_id_image = fn_get_department_id_image($department_id);
        fn_delete_image_pairs($department_id_image, 'department');
        db_query("DELETE FROM ?:department_images WHERE department_id = ?i", $department_id);
        fn_delete_image_pair ($department_id_image, 'department');
}


/**
 * Gets the image of the department
 *
 * @param int $department_id Department identificator
 * 
 * @return int $department_id Department identificator
 */
function fn_get_department_id_image($department_id)
{
    $department_id = db_get_field("SELECT department_id FROM ?:department_images WHERE department_id = ?i", $department_id);
    $department_id = isset($department_id) ? $department_id : null;

    return $department_id;
}


/**
 * Sets the link of the department with the image
 *
 * @param int $department_id Department identificator
 * @param array $pair_data   Image data
 */
function fn_departments_image_all_links($department_id, $pair_data)
{
    if (isset($pair_data)) {
        $pair_id = reset($pair_data);
        $department_id_image = db_query("INSERT INTO ?:department_images (department_id) VALUE (?i)", $department_id);
    
    }
}

/**
 * Deletes department and all related data
 *
 * @param int $department_id Department identificator
 */
function fn_delete_department_by_id($department_id)
{
    if (!empty($department_id)) {
        fn_department_image_delete($department_id);
        db_query("DELETE FROM ?:departments WHERE department_id = ?i", $department_id);
        db_query("DELETE FROM ?:department_descriptions WHERE department_id = ?i", $department_id);
        db_query("DELETE FROM ?:department_images WHERE department_id = ?i", $department_id);
    }
}
