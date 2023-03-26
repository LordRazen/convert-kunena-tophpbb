<?php
# Autoload for fully qualified classnames
require_once(dirname(__FILE__)  . '/vendor/autoload.php');

# Basic Settings
set_time_limit(0);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', 0);
error_reporting(E_ALL);

# Dir
const DIR_BASE  = __DIR__;
const DIR_WORK = DIR_BASE . '/work/';
const DIR_AVATARS = DIR_WORK . 'avatars/';
const DIR_ATTACHMENTS = DIR_WORK . 'attachments/';

# Init Folders
if (!is_dir(DIR_WORK)) mkdir(DIR_WORK);
if (!is_dir(DIR_AVATARS)) mkdir(DIR_AVATARS);
if (!is_dir(DIR_ATTACHMENTS)) mkdir(DIR_ATTACHMENTS);
