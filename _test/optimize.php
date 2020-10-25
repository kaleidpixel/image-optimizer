<?php
require_once dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
require_once dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'ImageOptimizer.php';

use KALEIDPIXEL\Module\ImageOptimizer;
use ProgressBar\Manager;

$error                  = '';
$optimizer              = ImageOptimizer::get_instance();
$optimizer->image_dir   = __DIR__ . DIRECTORY_SEPARATOR . 'images';
$optimizer->command_dir = dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'bin';
$images                 = $optimizer->get_file_list();
$progress               = new Manager( 0, ( $images !== false ) ? count( $images ) : 0 );

/**
 * Check error.
 */
switch ( true ) {
    case $images === false:
        $error = 'The directory could not found. Please make sure that the directory exists.';
        break;
    case is_array( $images ) && empty( $images ):
        $error = 'Image files (jpeg, png, gif, svg) could not found.';
        break;
}

if ( ! empty( $error ) ) {
    echo "Error: {$error}" . PHP_EOL;

    unset( $error );
    exit(1);
}

/**
 * Run.
 */
foreach ( $images as $k => $i ) {
    $progress->advance();

    $optimizer->optimize( $i );
    $optimizer->convert_to_webp( $i );

    unset( $images[ $k ] );
}

echo 'Complete!' . PHP_EOL;
exit(0);
