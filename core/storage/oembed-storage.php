<?php

/**
 * Handles the local storage on the server
 * 
 * This allows to store the files on the same server where WordPress is installed 
 * 
 */
class MPP_Oembed_Storage_Manager extends MPP_Storage_Manager {

	private static $instance;
	/**
	 *
	 * @var WP_oEmbed 
	 */
	private $oembed; 
	
	private $upload_errors = array();

	private function __construct() {

		$this->oembed = new WP_oEmbed();
		
		// $this->setup_upload_errors();
	}

	/**
	 * 
	 * @return MPP_Oembed_Storage_Manager
	 */
	public static function get_instance() {

		if ( ! isset( self::$instance ) )
			self::$instance = new self();

		return self::$instance;
	}
	
	/**
	 * Get the source url for the given size
	 * 
	 * @param string $type names of the various image sizes(thumb, mid,etc)
	 * @param int $id ID of the media
	 * 
	 * @return string source( absoulute url) of the media
	 * 
	 */
	public function get_src( $type = null, $id = null ) {
		//ID must be given
		if ( ! $id )
			return '';
		
		
		$url = wp_get_attachment_url( $id );

		if ( ! $type )
			return $url; //original media url

		$meta = wp_get_attachment_metadata( $id );
		
		//if size info is not available, return original src
		if ( empty( $meta[ 'sizes' ][ $type ][ 'file' ] ) )
			return $url; //return original size
		
		$base_url = str_replace( wp_basename( $url ), '', $url );

		$src = $base_url . $meta[ 'sizes' ][ $type ][ 'file' ];

		return $src;
	}

	/**
	 * Get the absolute path to a file ( file system path like /home/xyz/public_html/wp-content/uploads/mediapress/members/1/xyz)
	 * 
	 * @param type $type
	 * @param type $id
	 * @return string
	 * 
	 */
	public function get_path( $type = null, $id = null ) {
		//ID must be given
		if ( ! $id )
			return '';

		$upload_info = wp_upload_dir();
		
		$base_dir	 = $upload_info[ 'basedir' ];


		$meta = wp_get_attachment_metadata( $id );

		$file = $meta[ 'file' ];

		$rel_dir_path = str_replace( wp_basename( $file ), '', $file );

		$dir_path = path_join( $base_dir, $rel_dir_path );



		if ( !$type )
			return path_join( $base_dir, $file );

		if ( empty( $meta[ 'sizes' ][ $type ][ 'file' ] ) )
			return '';



		$abs_path = path_join( $dir_path, $meta[ 'sizes' ][ $type ][ 'file' ] );

		return $abs_path;
	}
	
	/**
	 * Uploads a file
	 * 
	 * @param type $file, name of the file field in html .e.g _mpp_file in <input type='file' name='_mpp_file' />
	 * @param array $args{
	 *	
	 *	@type string $component
	 *	@type int $component_id
	 *	@type int $gallery_id
	 * 
	 * }
	 * 
	 * @return boolean
	 */
	public function upload( $file, $args ) {

		
		
		extract( $args );

		if ( empty( $file_id ) )
			return false;

		//setup error
		$this->setup_upload_errors( $component_id );
		
		//$_FILE['_mpp_file']
		$file	 = $file[ $file_id ];

		$unique_filename_callback = null;


		//include from wp-admin dir for media processing
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		if ( !function_exists( 'mpp_handle_upload_error' ) ) {

			function mpp_handle_upload_error( $file, $message ) {

				return array( 'error' => $message );
			}

		}

		$upload_error_handler = 'mpp_handle_upload_error';

		// All tests are on by default. Most can be turned off by $overrides[{test_name}] = false;
		$test_form	 = true;
		$test_size	 = true;
		$test_upload = true;

		// If you override this, you must provide $ext and $type!!!!
		$test_type	 = true;
		$mimes		 = false;

		// Install user overrides. Did we mention that this voids your warranty?
		if ( !empty( $overrides ) && is_array( $overrides ) )
			extract( $overrides, EXTR_OVERWRITE );



		// A successful upload will pass this test. It makes no sense to override this one.
		if ( $file[ 'error' ] > 0 )
			return call_user_func( $upload_error_handler, $file, $this->upload_errors[ $file[ 'error' ] ] );

		// A non-empty file will pass this test.
		if ( $test_size && !($file[ 'size' ] > 0 ) ) {
			if ( is_multisite() )
				$error_msg	 = __( 'File is empty. Please upload something more substantial.', 'mediapress' );
			else
				$error_msg	 = __( 'File is empty. Please upload something more substantial. This error could also be caused by uploads being disabled in your php.ini or by post_max_size being defined as smaller than upload_max_filesize in php.ini.', 'mediapress' );
			return call_user_func( $upload_error_handler, $file, $error_msg );
		}

		// A properly uploaded file will pass this test. There should be no reason to override this one.
		if ( $test_upload && !@ is_uploaded_file( $file[ 'tmp_name' ] ) )
			return call_user_func( $upload_error_handler, $file, __( 'Specified file failed upload test.' ) );


		// A correct MIME type will pass this test. Override $mimes or use the upload_mimes filter.
		if ( $test_type ) {
			$wp_filetype = wp_check_filetype_and_ext( $file[ 'tmp_name' ], $file[ 'name' ], $mimes );

			extract( $wp_filetype );

			// Check to see if wp_check_filetype_and_ext() determined the filename was incorrect
			if ( $proper_filename )
				$file[ 'name' ] = $proper_filename;

			if ( (!$type || !$ext ) && !current_user_can( 'unfiltered_upload' ) )
				return call_user_func( $upload_error_handler, $file, __( 'Sorry, this file type is not permitted for security reasons.', 'mediapress' ) );

			if ( !$ext )
				$ext = ltrim( strrchr( $file[ 'name' ], '.' ), '.' );

			if ( !$type )
				$type = $file[ 'type' ];
		} else {
			$type = '';
		}



		// A writable uploads dir will pass this test. Again, there's no point overriding this one.
		if ( !( ( $uploads = $this->get_upload_dir( $args ) ) && false === $uploads[ 'error' ] ) ) {

			return call_user_func( $upload_error_handler, $file, $uploads[ 'error' ] );
		}

		$filename = wp_unique_filename( $uploads[ 'path' ], $file[ 'name' ], $unique_filename_callback );

		// Move the file to the uploads dir
		$new_file = $uploads[ 'path' ] . "/$filename";

		if ( !file_exists( $uploads[ 'path' ] ) )
			wp_mkdir_p( $uploads[ 'path' ] );

		if ( false === @ move_uploaded_file( $file[ 'tmp_name' ], $new_file ) ) {
			if ( 0 === strpos( $uploads[ 'basedir' ], ABSPATH ) )
				$error_path	 = str_replace( ABSPATH, '', $uploads[ 'basedir' ] ) . $uploads[ 'subdir' ];
			else
				$error_path	 = basename( $uploads[ 'basedir' ] ) . $uploads[ 'subdir' ];

			return $upload_error_handler( $file, sprintf( __( 'The uploaded file could not be moved to %s.', 'mediapress' ), $error_path ) );
		}

		// Set correct file permissions
		$stat	 = stat( dirname( $new_file ) );
		$perms	 = $stat[ 'mode' ] & 0000666;
		@ chmod( $new_file, $perms );

		// Compute the URL
		$url = $uploads[ 'url' ] . "/$filename";

		$this->invalidate_transient( $component, $component_id );

		return apply_filters( 'mpp_handle_upload', array( 'file' => $new_file, 'url' => $url, 'type' => $type ), 'upload' );
	}
	
	/**
	 * save binary data
	 * 
	 * @param type $name
	 * @param type $bits
	 * @param type $args
	 * @return type
	 */
	public function upload_bits( $name, $bits, $upload ) {

		if ( empty( $name ) )
			return array( 'error' => __( 'Empty filename', 'mediapress' ) );

		$wp_filetype = wp_check_filetype( $name );

		if ( !$wp_filetype[ 'ext' ] && !current_user_can( 'unfiltered_upload' ) )
			return array( 'error' => __( 'Invalid file type', 'mediapress' ) );
		
		
		if( ! $upload['path'] )
			return false;

		$upload_bits_error = apply_filters( 'mpp_upload_bits', array( 'name' => $name, 'bits' => $bits, 'path' => $upload['path'] ) );

		if ( !is_array( $upload_bits_error ) ) {
			$upload[ 'error' ] = $upload_bits_error;
			return $upload;
		}

		$filename = wp_unique_filename( $upload['path'], $name );

		$new_file = trailingslashit( $upload['path'] ) . "$filename";
		
		if ( !wp_mkdir_p( dirname( $new_file ) ) ) {
			

			$message = sprintf( __( 'Unable to create directory %s. Is its parent directory writable by the server?' ), dirname( $new_file  ) );
			return array( 'error' => $message );
		}

		$ifp = @ fopen( $new_file, 'wb' );
		if ( !$ifp )
			return array( 'error' => sprintf( __( 'Could not write file %s', 'mediapress' ), $new_file ) );

		@fwrite( $ifp, $bits );
		fclose( $ifp );
		clearstatcache();

		// Set correct file permissions
		$stat	 = @ stat( dirname( $new_file ) );
		$perms	 = $stat[ 'mode' ] & 0007777;
		$perms	 = $perms & 0000666;
		@ chmod( $new_file, $perms );
		clearstatcache();

		// Compute the URL
		$url = $upload[ 'url' ] . "/$filename";

		return array( 'file' => $new_file, 'url' => $url, 'error' => false );
	}	
	/**
	 * Extract meta from uploaded data 
	 * 
	 * @param type $uploaded
	 * @return type
	 */
	public function get_meta( $uploaded ) {

		$meta = array();

		$url	 = $uploaded[ 'url' ];
		$type	 = $uploaded[ 'type' ];
		$file	 = $uploaded[ 'file' ];


		//match mime type
		if ( preg_match( '#^audio#', $type ) ) {
			$meta = wp_read_audio_metadata( $file );

			// use image exif/iptc data for title and caption defaults if possible
		} else {
			$meta = @wp_read_image_metadata( $file );
		}


		return $meta;
	}

	/**
	 * Generate meta data for the media
	 *
	 * @since 1.0.0
	 *	
	 * @access  private
	 * @param int $attachment_id Media ID  to process.
	 * @param string $file Filepath of the Attached image.
	 * 
	 * @return mixed Metadata for attachment.
	 */
	public function generate_metadata( $attachment_id, $file ) {
		
		$attachment = get_post( $attachment_id );
		
		$mime_type = get_post_mime_type( $attachment );
		
		$metadata	 = array();
		
		if ( preg_match( '!^image/!', $mime_type ) && file_is_displayable_image( $file ) ) {
			
			$imagesize			 = getimagesize( $file );
			
			$metadata['width']	 = $imagesize[ 0 ];
			$metadata['height']	 = $imagesize[ 1 ];

			// Make the file path relative to the upload dir
			$metadata[ 'file' ] = _wp_relative_upload_path( $file );

			//get the registered media sizes
			$sizes = mpp_get_media_sizes();


			$sizes = apply_filters( 'mpp_intermediate_image_sizes', $sizes, $attachment_id );

			if ( $sizes ) {
				
				$editor = wp_get_image_editor( $file );

				
				if ( !is_wp_error( $editor ) )
					$metadata[ 'sizes' ] = $editor->multi_resize( $sizes );
				
			} else {
				
				$metadata[ 'sizes' ] = array();
			}

			// fetch additional metadata from exif/iptc
			$image_meta				 = wp_read_image_metadata( $file );
			
			if ( $image_meta )
				$metadata[ 'image_meta' ]	 = $image_meta;
			
		} elseif ( preg_match( '#^video/#', $mime_type ) ) {
			
			$metadata = wp_read_video_metadata( $file );
			
		} elseif ( preg_match( '#^audio/#',  $mime_type) ) {
			
			$metadata = wp_read_audio_metadata( $file );
			
		}
		
		$dir_path = trailingslashit( dirname( $file ) ) .'covers';
		$url = wp_get_attachment_url( $attachment_id );
		$base_url = str_replace( wp_basename( $url ), '', $url );
		
		//processing for audio/video cover
		if ( !empty( $metadata[ 'image' ][ 'data' ] ) ) {
			$ext = '.jpg';
			switch ( $metadata[ 'image' ][ 'mime' ] ) {
				case 'image/gif':
					$ext = '.gif';
					break;
				case 'image/png':
					$ext = '.png';
					break;
			}
			$basename	 = str_replace( '.', '-', basename( $file ) ) . '-image' . $ext;
			$uploaded	 = $this->upload_bits( $basename, $metadata[ 'image' ][ 'data' ] , array( 'path'=> $dir_path, 'url' => $base_url ) );
			if ( false === $uploaded[ 'error' ] ) {
				$attachment			 = array(
					'post_mime_type' => $metadata[ 'image' ][ 'mime' ],
					'post_type'		 => 'attachment',
					'post_content'	 => '',
				);
				$sub_attachment_id	 = wp_insert_attachment( $attachment, $uploaded[ 'file' ] );
				$attach_data		 = $this->generate_metadata( $sub_attachment_id, $uploaded[ 'file' ] );
				wp_update_attachment_metadata( $sub_attachment_id, $attach_data );
				//if the option is set to set post thumbnail
				if( mpp_get_option( 'set_post_thumbnail' ) )
					mpp_update_media_meta( $attachment_id, '_thumbnail_id', $sub_attachment_id );
				//set the cover id
				mpp_update_media_cover_id( $attachment_id, $sub_attachment_id );
			}
		}

		// remove the blob of binary data from the array
		if ( isset( $metadata[ 'image' ][ 'data' ] ) )
			unset( $metadata[ 'image' ][ 'data' ] );

		return apply_filters( 'mpp_generate_metadata', $metadata, $attachment_id );
	}

	

	/**
	 * Delete all the files associated with a Media
	 * 
	 * @global type $wpdb
	 * @param type $id
	 * @return boolean
	 */
	public function delete( $media_id ) {
		
		$media			 = mpp_get_media( $media_id );
		$meta			 = wp_get_attachment_metadata( $media_id );
		$backup_sizes	 = get_post_meta( $media_id, '_wp_attachment_backup_sizes', true );
		$file			 = get_attached_file( $media_id );

		//relatiev path from uploads directory to the current directory

		$rel_path = str_replace( wp_basename( $file ), '', $file );
		///echo "Rel path: $rel_path <br><br>";
		//$media = mpp_get_media( $media_id );
		
		//$upload_dir		 = wp_upload_dir();
		//$base_upload_dir = trailingslashit( $upload_dir['basedir'] ); //

		$gallery_dir = trailingslashit($rel_path ); //get the file system path to current gallery upload dir 

		

		//if ( is_multisite() )
			delete_transient( 'dirsize_cache' );

		do_action( 'mpp_before_media_files_delete', $media_id );

		delete_metadata( 'post', null, '_thumbnail_id', $media_id, true ); // delete all for any posts.
		
		$sizes = isset( $meta['sizes'] ) ? $meta['sizes'] : array() ;
		// remove intermediate and backup images if there are any
		foreach ( $sizes as $size ) {
			/** This filter is documented in wp-admin/custom-header.php */
			$media_file = apply_filters( 'mpp_delete_file', $size[ 'file' ] );
			
			@ unlink( path_join( $gallery_dir, $media_file ) );
		}

		
		$file = apply_filters( 'mpp_delete_file', $file );
		
		
		if ( !empty( $file ) )
			@ unlink( $base_upload_dir . $file );
		
		$this->invalidate_transient( $media->component, $media->component_id );
		return true;
	}

	/**
	 * Calculate the Used space by a component
	 * 
	 * @see mpp_get_used_space
	 * 
	 * @access private do not call it directly, use mpp_get_used_space instead
	 * 
	 * @param type $component
	 * @param type $component_id
	 * @return int
	 */
	public function get_used_space( $component, $component_id ) {

		//let us check for the transient as space calculation is bad everytime


		$key = "mpp_space_used_by_{$component}_{$component_id}"; //transient key

		$used_space = get_transient( $key );
		if ( ! $used_space ) {

			$dir_name = trailingslashit( $this->get_component_base_dir( $component, $component_id ) ); //base gallery directory for owner

			if ( !is_dir( $dir_name ) || !is_readable( $dir_name ) )
				return 0; //we don't know the usage or no usage


			$dir = dir( $dir_name );

			$size = 0;

			while ( $file = $dir->read() ) {

				if ( $file != '.' && $file != '..' ) {

					if ( is_dir( $dir_name . $file ) ) {
						$size += get_dirsize( $dir_name . $file );
					} else {

						$size += filesize( $dir_name . $file );
					}
				}
			}
			
			$dir->close();
			set_transient( $key, $size, DAY_IN_SECONDS );

			$used_space = $size;
		}

		$used_space = $used_space / 1024 / 1024;

		return $used_space;
	}

	
	public function get_errors() {
		
	}

	/**
	 * Server can handle upload?
	 * 
	 * @return boolean
	 */
	public function can_handle() {

		return true;//in future we may check the url for provider
	}

	
	/**
	 * Possible upload errors
	 */
	public function setup_upload_errors( $component ) {

		

		$this->upload_errors = array(
		
		);
	}

}

/**
 * Singleton Instance of MPP_Oembed_Storage_Manager
 * 
 * @return MPP_Oembed_Storage_Manager
 */
function mpp_oembed_storage() {

	return MPP_Oembed_Storage_Manager::get_instance();
}
