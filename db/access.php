<?php

/**
 * View HML resource
 *
 * @since 2.0
 * @package    repository
 * @subpackage helix_media_lib
 * @author     Tim Williams <tmw@autotrain.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$capabilities = array
(
    'repository/helix_media_lib:view' => array
       (
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array
            (
                'editingteacher' => CAP_ALLOW,
                'manager' => CAP_ALLOW
            )
        ) ,

    'repository/helix_media_lib:searchall' => array
        (
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array
            (
                'manager' => CAP_ALLOW
            )
        ) 
);
