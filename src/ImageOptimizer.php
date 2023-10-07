<?php
/**
 * PHP 5.6 or later
 *
 * @package    KALEIDPIXEL
 * @author     KUCKLU <hello@kuck1u.me>
 * @copyright  2018 Kaleid Pixel
 * @license    GNU General Public License v2.0 or later version
 * @version    0.3.0
 **/

namespace KALEIDPIXEL\Module;

if ( realpath( $_SERVER['SCRIPT_FILENAME'] ) === realpath( __FILE__ ) ) {
	exit;
}

use ProgressBar\Manager;
use enshrined\svgSanitize\Sanitizer;

/**
 * Class ImageOptimizer
 *
 * @package KALEIDPIXEL
 */
class ImageOptimizer {
	/**
	 * @var array Holds the instance of this class
	 */
	private static $instance = array();

	/**
	 * @var string Path of the directory where image files are stored.
	 */
	public $image_dir = './images';

	/**
	 * @var string Path of the directory where command binaries are saved.
	 */
	public $command_dir = './bin';

	/**
	 * @var string
	 */
	public $phar_name = 'ImageOptimizer';

	/**
	 * @var string
	 */
	private $with = 'with';

	/**
	 * Instance.
	 *
	 * @return self
	 */
	public static function get_instance() {
		$class = get_called_class();

		if ( !isset( self::$instance[$class] ) ) {
			self::$instance[$class] = new $class();
		}

		return self::$instance[$class];
	}

	/**
	 * Get my class.
	 *
	 * @return string
	 */
	public static function get_called_class() {
		return get_called_class();
	}

	/**
	 * ImageOptimizer constructor.
	 */
	protected function __construct() {
	}

	/**
	 * Get the mime type of the file from the file path.
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	public function get_mime_type( $path ) {
		return mime_content_type( $path );
	}

	public function escapepath( $path ) {
		return escapeshellarg( $path );
	}

	public function with( $v ) {
		return $v;
	}

	/**
	 * Check if the type of interface between the web server and PHP is CLI.
	 *
	 * @return bool
	 */
	public static function is_cli() {
		return (
			defined( 'STDIN' ) ||
			PHP_SAPI === 'cli' ||
			( stristr( PHP_SAPI, 'cgi' ) && getenv( 'TERM' ) ) ||
			array_key_exists( 'SHELL', $_ENV ) ||
			( empty( $_SERVER['REMOTE_ADDR'] ) && !isset( $_SERVER['HTTP_USER_AGENT'] ) && count( $_SERVER['argv'] ) > 0 ) ||
			!array_key_exists( 'REQUEST_METHOD', $_SERVER )
		);
	}

	/**
	 * Check if the type of interface is PHAR.
	 *
	 * @return bool
	 */
	public static function is_phar() {
		return !!\Phar::running( false );
	}

	/**
	 * Delete the directory and all its contents.
	 *
	 * @param $path
	 *
	 * @return void
	 */
	public static function rmdirAll( $path ) {
		if ( !file_exists( $path ) ) {
			return;
		}

		if ( is_file( $path ) ) {
			unlink( $path );

			return;
		}

		if ( $handle = opendir( $path ) ) {
			while ( false !== ( $item = readdir( $handle ) ) ) {
				if ( $item === '.' || $item === '..' ) {
					continue;
				}

				self::rmdirAll( $path . DIRECTORY_SEPARATOR . $item );
			}

			closedir( $handle );
			rmdir( $path );
		}
	}

	/**
	 * Optimize all images.
	 */
	public function doing( $mode = '' ) {
		switch ( $mode ) {
			case 'iterator':
			default:
				$images = $this->get_file_list();
				break;
			case 'glob':
				$images = $this->get_file_list_in_glob();
				break;
		}

		switch ( true ) {
			case $images === false:
				$error = 'The directory could not found. Please make sure that the directory exists.';
				break;
			case is_array( $images ) && empty( $images ):
				$error = 'Image files (jpeg, png, gif, svg) could not found.';
				break;
		}

		if ( !empty( $error ) ) {
			echo "Error: {$error}" . PHP_EOL;

			unset( $error );
			exit( 1 );
		}

		$progress = new Manager( 0, ( $images !== false ) ? count( $images ) : 0 );

		foreach ( $images as $k => $v ) {
			$progress->advance();
			$this->optimize( $v );
			$this->convert_to_webp( $v );
			$this->convert_to_avif( $v );
			unset( $images[$k] );
		}

		if ( $this->is_phar() === true ) {
			$phar_bin = dirname( \Phar::running( false ) ) . DIRECTORY_SEPARATOR . '.' . $this->phar_name;

			if ( is_dir( $phar_bin ) ) {
				$this->rmdirAll( $phar_bin );
			}

			unset( $phar_bin );
		}

		echo 'Complete!' . PHP_EOL;
		exit( 0 );
	}

	/**
	 * Create list of all image files in a specific directory.
	 *
	 * @return bool|array
	 */
	public function get_file_list() {
		$this->image_dir = self::_add_trailing_slash( $this->image_dir );

		if ( is_dir( $this->image_dir ) ) {
			$result   = array();
			$iterator = new \RecursiveDirectoryIterator( $this->image_dir, \FileSystemIterator::SKIP_DOTS );
			$iterator = new \RecursiveIteratorIterator( $iterator );
			$iterator = new \RegexIterator( $iterator, '/^.+\.(jpe?g|png|gif|svg)$/i', \RecursiveRegexIterator::MATCH );

			foreach ( $iterator as $info ) {
				if ( $info->isFile() ) {
					$result[] = $info->getPathname();
				}
			}

			unset( $iterator );
		} else {
			$result = false;
		}

		return $result;
	}

	/**
	 * Create list of all image files in a specific directory.
	 *
	 * @param string $dir
	 *
	 * @return array
	 */
	public function get_file_list_in_glob( $dir = '' ) {
		$result = array();
		$dir    = ( empty( $dir ) ) ? $this->image_dir : $dir;
		$dir    = self::_delete_trailing_slash( $dir );

		if ( is_dir( $dir ) ) {
			$files = glob( $dir . DIRECTORY_SEPARATOR . "*", GLOB_BRACE );

			foreach ( $files as $v ) {
				if ( is_file( $v ) ) {
					switch ( self::get_mime_type( $v ) ) {
						case 'image/jpeg':
						case 'image/png':
						case 'image/gif':
						case 'image/svg+xml':
							$result[] = $v;
							break;
					}
				}

				if ( is_dir( $v ) ) {
					$result = array_merge( $result, self::get_file_list_in_glob( $v ) );
				}
			}

			unset( $files );
		}

		return $result;
	}

	/**
	 * Image Optimize.
	 *
	 * @param string $file
	 */
	public function optimize( $file ) {
		switch ( self::get_mime_type( $file ) ) {
			case 'image/jpeg':
				$command = self::get_binary_path( 'cjpeg' );

				exec( "{$command} -quality 75 -optimize -outfile {$this->with( $this->escapepath( "$file-tmp" ) )} {$this->with( $this->escapepath( $file ) )} 2>&1", $result );

				if ( file_exists( "$file-tmp" ) ) {
					unlink( $file );
					rename( "$file-tmp", $file );
				}
				break;
			case 'image/png':
				$pngquant = self::get_binary_path( 'pngquant' );
				$oxipng   = self::get_binary_path( 'oxipng' );

				exec( "{$pngquant} --quality 0-100 --verbose 256 --floyd=1 --speed 1 --force --output={$this->with( $this->escapepath( $file ) )} {$this->with( $this->escapepath( $file ) )} 2>&1", $result );
				exec( "{$oxipng} -o 4 -i 0 --strip all {$this->with( $this->escapepath( $file ) )} 2>&1", $result );
				break;
			case 'image/gif':
				$command = self::get_binary_path( 'gifsicle' );

				exec( "{$command} -O2 {$this->with( $this->escapepath( $file ) )} > {$this->with( $this->escapepath( $file ) )} 2>&1", $result );
				break;
			case 'image/svg+xml':
				$sanitizer = new Sanitizer();

				$sanitizer->minify( true );

				$dirtySVG = file_get_contents( $file );
				$cleanSVG = $sanitizer->sanitize( $dirtySVG );

				if ( $cleanSVG === false ) {
					break;
				}

				file_put_contents( $file, $cleanSVG );
				break;
		}
	}

	/**
	 * Generate image in webp format.
	 *
	 * @param string $file
	 */
	public function convert_to_webp( $file ) {
		switch ( self::get_mime_type( $file ) ) {
			case 'image/jpeg':
			case 'image/png':
				$out     = self::get_filename( $file );
				$command = self::get_binary_path( 'cwebp' );

				exec( "{$command} {$this->with( $this->escapepath( $file ) )} -o {$this->with( $this->escapepath( $out ) )} 2>&1", $result );
				break;
		}
	}

	/**
	 * Generate image in avif format.
	 *
	 * @param string $file
	 */
	public function convert_to_avif( $file ) {
		switch ( self::get_mime_type( $file ) ) {
			case 'image/jpeg':
			case 'image/png':
				$command = self::get_binary_path( 'cavif' );

				exec( "{$command} --quality 80 --depth=8 --overwrite --speed=4 {$this->with( $this->escapepath( $file ) )} 2>&1", $result );
				break;
		}
	}

	/**
	 *  Get the file name of webp.
	 *
	 * @param $file
	 * @param $type
	 *
	 * @return array|string|string[]
	 */
	public function get_filename( $file, $type = 'webp' ) {
		$ext = pathinfo( $file, PATHINFO_EXTENSION );

		switch ( $type ) {
			default:
			case 'webp':
				return str_replace( ".{$ext}", '.webp', $file );
			case 'avif':
				return str_replace( ".{$ext}", '.avif', $file );
		}
	}

	/**
	 * Generate path of binary file.
	 *
	 * @param string $bin
	 *
	 * @return string
	 */
	public function get_binary_path( $bin ) {
		$os_dir            = '';
		$this->command_dir = self::_delete_trailing_slash( $this->command_dir );
		$uname             = php_uname( 'm' );
		$bin_name          = $bin;

		switch ( PHP_OS ) {
			case 'Darwin':
			case 'FreeBSD':
			case 'Linux':
				if ( $uname === 'x86_64' && $bin === 'cjpeg' ) {
					$bin = 'amd64' . DIRECTORY_SEPARATOR . $bin;
				} elseif ( $uname === 'aarch64' && $bin === 'cjpeg' ) {
					$bin = 'arm64' . DIRECTORY_SEPARATOR . $bin;
				}
				break;
		}

		switch ( PHP_OS ) {
			case 'WINNT':
				$os_dir   = 'win';
				$bin      = $bin . '.exe';
				$bin_name = $bin;
				break;
			case 'Darwin':
				$os_dir = 'mac';
				break;
			case 'FreeBSD':
				$os_dir = 'fbsd';
				break;
			case 'Linux':
				$os_dir = 'linux';
				break;
		}

		$command = $this->command_dir . DIRECTORY_SEPARATOR . $os_dir . DIRECTORY_SEPARATOR . $bin;

		if ( $this->is_phar() === true ) {
			$from     = $command;
			$phar_bin = dirname( \Phar::running( false ) ) . DIRECTORY_SEPARATOR . '.' . $this->phar_name;
			$command  = $phar_bin . DIRECTORY_SEPARATOR . $bin_name;

			if ( !file_exists( $command ) ) {
				if ( !is_dir( $this->_add_trailing_slash( $phar_bin ) ) ) {
					mkdir( $this->_add_trailing_slash( $phar_bin ), 0755, true );
				}

				file_put_contents( $command, file_get_contents( $from ) );
			}
		}

		if ( !is_executable( $command ) ) {
			chmod( $command, 0755 );
		}

		return $this->_sanitize_dir_name( $command );
	}

	/**
	 * Sanitize dir name.
	 *
	 * @param string $dir_name
	 *
	 * @return string
	 */
	private function _sanitize_dir_name( $dir_name = '' ) {
		$dir_name = str_replace( ' ', '\ ', $dir_name );

		return $dir_name;
	}

	/**
	 * Delete trailing slash.
	 *
	 * @param string $str
	 *
	 * @return string
	 */
	private function _delete_trailing_slash( $str = '' ) {
		return rtrim( $str, DIRECTORY_SEPARATOR );
	}

	/**
	 * Add trailing slash.
	 *
	 * @param string $str
	 *
	 * @return string
	 */
	private function _add_trailing_slash( $str = '' ) {
		$str = self::_delete_trailing_slash( $str );

		return $str . DIRECTORY_SEPARATOR;
	}
}
