<?php
// SPDX-License-Identifier: GPL-3.0-or-later
defined('MOODLE_INTERNAL') || die();
$observers = [['eventname' => '\\core\\event\\user_deleted', 'callback' => '\\auth_eudiwallet\\observer::user_deleted']];
