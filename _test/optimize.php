<?php
set_time_limit( 0 );

require_once dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
require_once dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'ImageOptimizer.php';

use KALEIDPIXEL\Module\ImageOptimizer;

$error                  = '';
$optimizer              = ImageOptimizer::get_instance();
$optimizer->image_dir   = __DIR__ . DIRECTORY_SEPARATOR . 'images';
$optimizer->command_dir = dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'bin';

$optimizer->doing();
