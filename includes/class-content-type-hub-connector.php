<?php
/**
 * Main plugin class file.
 *
 * @package sustainum-h5p-content-type-hub-manager
 */

namespace Sustainum\H5PContentTypeHubManager;

// as suggested by the WordPress community.
defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

/**
 * Main plugin class.
 */
class ContentTypeHubConnector {
  // Constants
  private const SLUG = 'sustainum-h5p-content-type-hub-manager';

  // Properties
  private $h5pFramework;

	/**
	 * Constructor.
	 */
	public function __construct() {
    $this->h5pFramework = \H5P_Plugin::get_instance()->get_h5p_instance( 'interface' );
	}

  /**
   * Fetch the latest content types from the H5P Content Type Hub.
   *
   * @return \stdClass An object containing the content types or an error message.
   */
  private static function fetch_latest_content_types() {
    // In theory, the UUID would need to be registered with the H5P Content Type Hub, but it does not check.
    $site_uuid = self::createUUID();
    $postdata = ['uuid' => $site_uuid];
    $endpoint_url = self::build_api_endpoint(null, 'content-types');

    $response = wp_remote_post($endpoint_url, [
      'body' => $postdata
    ]);

    $result = new \stdClass();

    $error_message = __('Error fetching content types: %s');

    if (is_wp_error($response)) {
      $result->error = sprintf($error_message, $response->get_error_message());
      return $result;
    }

    if (wp_remote_retrieve_response_code($response) !== 200) {
      $result->error = sprintf($error_message, wp_remote_retrieve_response_code($response));
      return $result;
    }

    $body = wp_remote_retrieve_body($response);
    try {
      $result = json_decode($body);
      if (json_last_error() !== JSON_ERROR_NONE) {
        $result->error = sprintf($error_message, json_last_error_msg());
      }
    }
    catch (\Exception $e) {
      $result->error = sprintf($error_message, $e->getMessage());
    }

    return $result;
	}

  /**
   * Create a UUID.
   *
   * @return string The UUID.
   */
  private static function createUUID()
  {
    return preg_replace_callback(
      "/[xy]/",
      function ($match) {
        $random = random_int(0, 15);
        $newChar = $match[0] === "x" ? $random : ($random & 0x3) | 0x8;
        return dechex($newChar);
      },
      "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx"
    );
  }

  /**
   * Build the API endpoint URL.
   *
   * @param string|null $machineName The machine name of the content type.
   * @param string $endpoint The endpoint to use - default is 'content-types', and we don't need another here.
   * @return string The complete API endpoint URL.
   */
  private static function build_api_endpoint($machineName = null, $endpoint = 'content-types') {
    $protocol = extension_loaded('openssl') ? 'http' : 'http';
    $endpoint_url_base = Options::get_endpoint_url_base();

    return "{$protocol}://{$endpoint_url_base}/{$endpoint}" . ($machineName ? "/{$machineName}" : '');
  }

  /**
   * Check if the content type is restricted.
   *
   * @param string $machine_name The machine name of the content type.
   * @param int $major The major version of the content type.
   * @param int $minor The minor version of the content type.
   * @return bool True if restricted, false otherwise.
   */
  private static function is_content_type_restricted($machine_name, $major, $minor) {
    global $wpdb;

    if (!isset($machine_name) || !isset($major) || !isset($minor)) {
      return false;
    }

    $table_name = $wpdb->prefix . 'h5p_libraries';
    $query = $wpdb->prepare(
      "SELECT restricted FROM {$table_name} WHERE name = %s AND major_version = %d AND minor_version = %d",
      $machine_name, $major, $minor
    );

    $result = (int) ($wpdb->get_var($query) ?? 0);

    return $result === 1;
  }

  /**
   * Update the H5P library information in the database.
   *
   * @param int $libraryId The ID of the library to update.
   * @param array $library The library information.
   */
  private static function update_h5p_library_information($libraryId, $library) {
    global $wpdb;

    if (!isset($libraryId) || !isset($library) || !is_array($library)) {
      return;
    }

    $params = array();
    if (array_key_exists('tutorial', $library)) {
      $params['tutorial_url'] = $library['tutorial'];
    }
    if (count($params) === 0) {
      return;
    }

    $table_name = $wpdb->prefix . 'h5p_libraries';
    $set_clause = implode(', ', array_map(function ($key) {
      return "{$key} = %s";
    }, array_keys($params)));

    $query = $wpdb->prepare(
      "UPDATE {$table_name} SET {$set_clause} WHERE id = %d",
      ...array_merge(array_values($params), [$libraryId])
    );

    $wpdb->query($query);
  }

  /**
   * Check if the core API version is compatible with the installed H5P version.
   *
   * @param object|null $core_api Object containing major and minor version properties.
   * @return bool True if compatible or no core API provided, false if not compatible.
   */
  private static function is_required_core_api($core_api) {
    if (empty($core_api)) {
      return true;
    }

    $h5p_major_version = \H5PCore::$coreApi['majorVersion'];
    $h5p_minor_version = \H5PCore::$coreApi['minorVersion'];

    $is_major_newer = $core_api->major > $h5p_major_version;
    $is_minor_newer = ($core_api->major === $h5p_major_version) &&
                      ($core_api->minor > $h5p_minor_version);

    return !($is_major_newer || $is_minor_newer);
  }

  /**
   * Fetch the library archive from the H5P Content Type Hub.
   *
   * @param array $library The library information.
   * @return \stdClass An object containing the result or error message.
   */
  private static function fetch_library_archive($library) {
    $result = new \stdClass();
    $result->error = null;

    $endpoint_url = self::build_api_endpoint($library['machineName'], 'content-types');
    $response = wp_remote_get($endpoint_url);

    if (is_wp_error($response)) {
      $result->error = sprintf(
        __('Error fetching content type %s: %s'),
        $library['machineName'],
        $response->get_error_message()
      );
      return $result;
    }

    $result->result = wp_remote_retrieve_body($response);
    return $result;
  }

  /**
   * Execute a callback with the 'manage_h5p_libraries' capability.
   *
   * @param callable $callback The callback to execute.
   * @return \stdClass An object containing the result or error message.
   */
  private static function execute_with_h5p_managing_rights($callback) {
    $result = new \stdClass();
    $result->error = null;

    $current_user_can_manage_libraries = current_user_can('manage_h5p_libraries');
    $current_user = wp_get_current_user();
    if (!$current_user_can_manage_libraries) {
      $current_user->add_cap('manage_h5p_libraries');
    }

    try {
      $result->result = call_user_func($callback);
    } catch (\Exception $e) {
      $result->error = $e->getMessage();
    }
    finally {
      if (!$current_user_can_manage_libraries) {
        $current_user->remove_cap('manage_h5p_libraries');
      }

      return $result;
    }
  }

  /**
   * Check if the H5P package is valid.
   *
   * @param \H5PValidator $h5pValidator The H5P validator instance.
   * @return bool True if valid, false otherwise.
   */
  private static function is_h5p_package_valid($h5pValidator) {
    return $h5pValidator->isValidPackage(true, false);
  }

  /**
   * Save the H5P package.
   *
   * @param \H5PStorage $storage The H5P storage instance.
   */
  private static function save_h5p_package($storage) {
    $storage->savePackage(NULL, NULL, TRUE);
  }

  /**
   * Leave H5P cleanly by cleaning up temporary files and silencing messages.
   *
   * @param string $target_folder_path The path to the target folder.
   * @param string $target_file_path The path to the target file.
   * @param \H5P_Plugin $h5pFramework The H5P framework instance.
   */
  private static function leave_h5p_cleanly($target_folder_path, $target_file_path, $h5pFramework) {
    self::clean_up_temporary_h5p_files($target_folder_path, $target_file_path);
    self::silence_h5p_messages($h5pFramework);
  }

  /**
   * Clean up temporary H5P files.
   *
   * @param string $target_folder_path The path to the target folder.
   * @param string $target_file_path The path to the target file.
   */
  private static function clean_up_temporary_h5p_files($target_folder_path, $target_file_path) {
    \H5PCore::deleteFileTree($target_folder_path);
    \H5PCore::deleteFileTree($target_file_path);
  }

  /**
   * Silence H5P messages to avoid displaying them in the UI.
   *
   * @param \H5P_Plugin $h5pFramework The H5P framework instance.
   */
  private static function silence_h5p_messages($h5pFramework) {
    $h5pFramework->getMessages('error');
    $h5pFramework->getMessages('info');
  }

  /**
   * Write the archive content to a temporary file.
   *
   * @param string $file_content The content of the file to write.
   * @param string $target_file_path The path to the target file.
   * @return \stdClass An object containing the result or error message.
   */
  private static function write_archive_to_temporary_file($file_content, $target_file_path) {
    $result = new \stdClass();
    $result->error = null;

    $file_handle = fopen($target_file_path, 'w');
    if (!$file_handle) {
      fclose($file_handle);
      $result->error = sprintf(
        __('Could not open file %s for writing.'),
        $target_file_path
      );
      return $result;
    }

    if (fwrite($file_handle, $file_content) === false) {
      fclose($file_handle);
      $result->error = sprintf(
        __('Could not write to file %s.'),
        $target_file_path
      );
      return $result;
    }

    fclose($file_handle);

    return $result;
  }

  /**
   * Check the library after installation.
   *
   * @param array $library The library information.
   * @param \H5PCore $h5pCore The H5P core instance.
   * @return \stdClass An object containing the result or error message.
   */
  private static function checkAfterInstall($library, $h5pCore) {
    $result = new \stdClass();
    $result->error = null;

    $versionedLibraryName = \H5PCore::libraryToString($library);
    $libraryJSON = $h5pCore->librariesJsonData[$versionedLibraryName];
    if (is_null($libraryJSON) || !array_key_exists('libraryId', $libraryJSON) ) {
      $result->error = sprintf(
        __('Error while installing content type %s: %s'),
        $versionedLibraryName,
        __('Library ID is missing.')
      );
    }
    else{
      $result->libraryId = $libraryJSON['libraryId'];
    }

    return $result;
  }

  /**
   * Install new content type versions.
   */
  public function install_new_content_type_versions() {
    $content_types = self::fetch_latest_content_types();
    if (!empty($content_types->error)) {
      error_log( SLUG . ': ' . $content_types->error );
      return;
    }

    foreach ($content_types->contentTypes as $content_type) {
      if (self::is_content_type_restricted($content_type->id, $content_type->version->major, $content_type->version->minor)) {
        continue; // Admin has restricted use of this content type.
      }

      if (!self::is_required_core_api($content_type->coreApiVersionNeeded)) {
        continue; // Content type to be installed is not compatible with the installed H5P core version.
      }

      $library = [
        'machineName' => $content_type->id,
        'majorVersion' => $content_type->version->major,
        'minorVersion' => $content_type->version->minor,
        'patchVersion' => $content_type->version->patch,
      ];

      if (isset($content_type->example)) {
          $library['example'] = $content_type->example;
      }
      if (isset($content_type->tutorial)) {
          $library['tutorial'] = $content_type->tutorial;
      }

      // Note that libraries which are not yet installed will not be installed
      $is_installed_library_newer_patched_version = $this->h5pFramework->isPatchedLibrary($library) === FALSE;
      if ($is_installed_library_newer_patched_version) {
        continue; // Content type is already installed and up to date.
      }

      $result = self::install_content_type($library);
      if (isset($result->error)) {
        error_log(SLUG . ': ' . $result->error);
      }
    }
	}

  /**
   * Install a content type.
   *
   * @param array $library The library information.
   */
  private static function install_content_type($library) {
    $result = new \stdClass();
    $result->error = null;

    // Not entirely sure, but to get fresh paths and validators, we need new instances here.
    $h5pFramework = \H5P_Plugin::get_instance()->get_h5p_instance( 'interface' );
    $h5pValidator = \H5P_Plugin::get_instance()->get_h5p_instance( 'validator' );
    $h5pCore = \H5P_Plugin::get_instance()->get_h5p_instance( 'core' );

    $file_content = self::fetch_library_archive($library);
    if (isset($file_content->error)) {
      $result->error = $file_content->error;
      return $result;
    }
    $file_content = $file_content->result;

    $target_file_path = $h5pFramework->getUploadedH5pPath(); // H5P will expect the archive here
    $write_result = self::write_archive_to_temporary_file($file_content, $target_file_path);
    if (isset($write_result->error)) {
      $result->error = $write_result->error;
      return $result;
    }

    $target_folder_path = $h5pFramework->getUploadedH5pFolderPath(); // H5P will extract files here during validation
    $validation_result = self::execute_with_h5p_managing_rights(function() use ($h5pValidator) {
      return self::is_h5p_package_valid($h5pValidator);
    });

    if (isset($validation_result->error)) {
      $result->error = $validation_result->error;
      self::leave_h5p_cleanly($target_folder_path, $target_file_path, $h5pFramework);
      return $result;
    } else if (!$validation_result->result) {
      $result->error = sprintf(
        __('Not a valid H5P package for content type %s: %s'),
        $library['machineName'],
        join(' / ', $h5pFramework->getMessages('error'))
      );
      self::leave_h5p_cleanly($target_folder_path, $target_file_path, $h5pFramework);
      return $result;
    }

    $storage = new \H5PStorage($h5pFramework, $h5pCore);
    $saved_result = self::execute_with_h5p_managing_rights(function() use ($storage) {
      return self::save_h5p_package($storage);
    });

    if (isset($saved_result->error)) {
      $result->error = $saved_result->error;
      self::leave_h5p_cleanly($target_folder_path, $target_file_path, $h5pFramework);
      return $result;
    }

    self::leave_h5p_cleanly($target_folder_path, $target_file_path, $h5pFramework);

    $installation_check_result = self::checkAfterInstall($library, $h5pCore);
    if (isset($installation_check_result->error)) {
      $result->error = $installation_check_result->error;
      return $result;
    }

    self::update_h5p_library_information($installation_check_result->libraryId, $library);

    $result->status = 'OK';

    return $result;
  }
}
