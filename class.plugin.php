<?php

if (!function_exists('add_action')) {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit();
}

class ciLogViewer
{
	const DOMAIN = "ciLogViewer";
	private $_file_view = false;
	private $_files = array();

	public function __construct()
	{
		$this->_file_view = new ciLogViewer_FileView($this);
	}

	public static function transformFilePath($file)
	{
		$path = realpath(self::getDefaultLogDirectory() . DIRECTORY_SEPARATOR . $file);

		return $path;
	}

	protected static function getDefaultLogDirectory()
	{
		$directory = apply_filters('log-viewer/default-log-directory', WP_CONTENT_DIR);
		return rtrim($directory, DIRECTORY_SEPARATOR);
	}

	public function hasFiles()
	{
		$this->getFiles();
		if (empty($this->_files)) {
			return false;
		}

		return true;
	}

	public function getFiles()
	{
		if (empty($this->_files)) {
			$this->_updateFiles();
		}

		return $this->_files;
	}

	private function _updateFiles()
	{
		$directories = [self::getDefaultLogDirectory()];
		$directories = apply_filters('log-viewer/directories', $directories);
		$files = [];
		foreach ($directories as $directory)
			try {
				$files = array_merge($files, $this->_getFilesInDirectory($directory));
			} catch (Throwable $e) {
				$this->_log('Error while looking for files in', $directory, $e->getMessage());
			}
		$filesBeforeFilter = $files;
		try {
			$files = apply_filters('log-viewer/log-files', $files);
			if (!is_array($files)) {
				$files = $filesBeforeFilter;
				$this->_log('Warning. An array was not returned from log-viewer/log-files filter.', var_export($files, true));
			}
			$this->_files = $files;
		} catch (Throwable $e) {
			$this->_log('Error while filter log files.', $e->getMessage());
			$this->_files = $filesBeforeFilter;
		}
	}

	private function _getFilesInDirectory(string $directory)
	{
		$realDirectory = realpath($directory);
		$files = [];
		if ($realDirectory) {
			$pattern = $realDirectory . DIRECTORY_SEPARATOR . "*.log";
			$f = glob($pattern);
			$str_rep = $realDirectory . DIRECTORY_SEPARATOR;
			foreach ($f as $i => $file) {
				$files[] = [
					'name' => str_replace($str_rep, "", $file),
					'path' => $file
				];
			}
		} else {
			$this->_log('Directory does not exist:', $directory);
		}
		return apply_filters('log-viewer/directory-log-files', $files, $directory);

	}

	/**
	 * @param $msg
	 */
	private function _log(...$msgs)
	{
		error_log(self::DOMAIN . ': ' . join(' ', $msgs));
	}
}

/**
 *
 */
final class ciLogViewer_FileView
	extends CI_WP_AdminSubPage
{
	private $_plugin = null;
	private $_selectedFileId = -1;
	private $_file = ['path' => '', 'name' => ''];
	private $_settings = array(
		'autorefresh' => 1,
		'display' => 'fifo',
		'refreshtime' => 15,
	);

	public function __construct($plugin)
	{
		$this->_plugin = $plugin;

		parent::_initialize(
			__('Files View'),
			__('Log Viewer'),
			'ciLogViewer',
			'manage_options',
			self::SUBMENU_PARENTSLUG_TOOLS
		);
	}

	public function onViewPage()
	{
		global $action, $file, $file2, $display, $autorefresh, $Apply;
		wp_reset_vars(['action', 'file', 'file2', 'display', 'autorefresh', 'Apply']);

		$this->_loadUserSettings();

		$file = $file2;

		$newSettings = $this->_settings;
		if ($Apply) {
			!$autorefresh ? $newSettings["autorefresh"] = 0 : $newSettings["autorefresh"] = 1;
			!$display ? $newSettings["display"] = $this->_settings["display"] : $newSettings["display"] = $display;
		}
		if ($this->_settings["autorefresh"] === 1) {
			?>
          <script type="text/javascript">
              setTimeout("window.location.replace(document.URL);", <?php echo $this->_settings["refreshtime"] * 1000 ?>);
          </script>
			<?php
		}
		if (is_user_logged_in()) {
			$this->_updateUserSettings($newSettings);
		}

		$this->_draw_header();

		if (!$this->_plugin->hasFiles()) {
			echo '<div id="message" class="updated"><p>', _e('No files found.'), '</p></div>';
			return;
		}

		$files = $this->_plugin->getFiles();

		if (isset($_REQUEST['file']))
			$this->_selectedFileId = intval(stripslashes($_REQUEST['file']));
		else
			$this->_selectedFileId = 0;

		if (isset($files[$this->_selectedFileId]))
			$this->_file = $files[$this->_selectedFileId];

		$writeable = is_writeable($this->_file['path']);
		if (!$writeable && !empty($this->_file['path'])) {
			$writeable = $this->makeWriteableFile($this->_file['path']);
		}
		if (!$writeable) {
			$action = false;
			?>
          <div id="message" class="updated">
              <p><?php _e('You can not edit file ( not writeable ).'); ?></p>
          </div>
			<?php
		}

		switch ($action) {
			case 'dump':
				$dumped = unlink($this->_file['path']);
				if ($dumped) :
					?>
                <div id="message" class="updated">
                    <p><?php _e('File dumped successfully.'); ?></p>
                </div>
					<?php return;
				else :
					?>
                <div id="message" class="error">
                    <p><?php _e('Could not dump file.'); ?></p>
                </div>
				<?php
				endif;
				break;
			case 'empty':
				$handle = fopen($this->_file['path'], 'w');
				if (!$handle) :
					?>
                <div id="message" class="error">
                    <p><?php _e('Could not open file.'); ?></p>
                </div>
				<?php
				endif;

				$handle = fclose($handle);
				if (!$handle) :
					?>
                <div id="message" class="error">
                    <p><?php _e('Could not empty file.'); ?></p>
                </div>
				<?php else : ?>
                <div id="message" class="updated">
                    <p><?php _e('File empty successfull.'); ?></p>
                </div>
				<?php
				endif;

				break;
			case 'break':
				if (!error_log('------', 0)) :
					?>
                <div id="message" class="error">
                    <p><?php _e('Could not update file.'); ?></p>
                </div>
				<?php else : ?>
                <div id="message" class="updated">
                    <p><?php _e('File updated successfully.'); ?></p>
                </div>
				<?php
				endif;

				break;
			default:
				break;
		}
		?>
       <div class="fileedit-sub">
           <strong>
				  <?php printf('%1$s <strong>%2$s</strong>', __('Showing'), str_replace(realpath(ABSPATH), "", $this->_file['path'])) ?>
           </strong>

           <div class="tablenav top">

				  <?php if ($writeable) : ?>

                  <div class="alignleft">
                      <form method="post" action="<?php echo $this->getPageUrl(); ?>">
                          <input type="hidden" value="<?php echo $this->_selectedFileId; ?>" name="file"/>
                          <input id="scrollto" type="hidden" value="0" name="scrollto">
                          <select name="action">
                              <option selected="selected" value="-1"><?php _e('File Actions'); ?></option>
                              <option value="dump"><?php _e('Dump'); ?></option>
                              <option value="empty"><?php _e('Empty'); ?></option>
                              <option value="break"><?php _e('Break'); ?></option>
                          </select>
								 <?php submit_button(__('Do'), 'button', 'Do', false); ?>
                      </form>
                  </div>

				  <?php endif; ?>
               <div class="alignright">
                   <form method="post" action="<?php echo $this->getPageUrl(); ?>">
                       <input type="hidden" value="<?php echo $this->_selectedFileId; ?>" name="file2"/>
                       <input type="checkbox" value="1" <?php checked(1 == $this->_settings['autorefresh']); ?>
                              name="autorefresh"/>
                       <label for="autorefresh">Autorefresh</label>
                       <select name="display">
                           <option <?php selected('fifo' == $this->_settings['display']); ?> value="fifo">FIFO</option>
                           <option <?php selected('filo' == $this->_settings['display']); ?> value="filo">FILO</option>
                       </select>
							 <?php submit_button(__('Apply'), 'button', 'Apply', false); ?>
                   </form>
               </div>
           </div>

       </div>
       <div id="templateside">
           <h3>Log Files</h3>
           <ul>
				  <?php foreach ($files as $i => $file) {
					  if ($this->_selectedFileId === $i) {
						  echo '<li class="highlight">';
					  } else {
						  echo '<li>';
					  }
					  echo '<a href="', $this->getPageUrl(), '&file=', $i, '">', $file['name'], '</a>';
					  echo '</li>';
				  } ?>
           </ul>
       </div>
       <div id="template">
           <div>
				  <?php if (!is_file($this->_file['path'])) : ?>
                  <div id="message" class="error">
                      <p><?php _e('Could not load file.'); ?></p>
                  </div>
				  <?php else : ?>
                  <textarea id="newcontent" name="newcontent" rows="25" cols="70"
                            readonly="readonly"><?php echo $this->_getCurrentFileContent(); ?></textarea>
				  <?php endif; ?>
               <div>
                   <h3><?php _e('Fileinfo'); ?></h3>
                   <dl>
                       <dt><?php _e('Fullpath:'); ?></dt>
                       <dd><?php echo $this->_file['path']; ?></dd>
                       <dt><?php _e('Last updated: '); ?></dt>
                       <dd><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), filemtime($this->_file['path'])); ?></dd>
                   </dl>
               </div>
           </div>
       </div>
		<?php

		$this->_draw_footer();

	}

	private function _loadUserSettings()
	{
		if (is_user_logged_in()) {
			$id = wp_get_current_user();
			$id = $id->ID;
			$optionskey = $id . "_log-viewer_settings";

			$settings = get_option($optionskey, false);
			if ($settings === false) {
				add_option($optionskey, $this->_settings);
			} elseif (!is_array($settings)) {
				update_option($optionskey, $this->_settings);
			} else {
				$this->_settings = $settings;
			}
		}
	}

	private function _updateUserSettings($settings)
	{
		if (is_user_logged_in()) {
			$id = wp_get_current_user();
			$id = $id->ID;
			$optionskey = $id . "_log-viewer_settings";
			if ($settings != $this->_settings) {
				update_option($optionskey, $settings);
				$this->_settings = $settings;
				//echo 'Update!!'; var_dump($settings);
			} else {
				//var_dump($settings);echo '<br/>';
				//var_dump($this->_settings);echo '<br/>';
				//echo 'Nix Upddate!!';
			}
		}
	}

	private function _draw_header()
	{
		echo '<div class="wrap"><div id="icon-tools" class="icon32"><br/></div><h2>', $this->_page_title, '</h2>';
	}

	private function makeWriteableFile(string $realfile)
	{
		return chmod($realfile, 0766);
	}

	private function _getCurrentFileContent()
	{
		if ($this->_settings['display'] == 'filo') {
			$result = implode(array_reverse(file($this->_file['path'])));
		} else {
			$result = file_get_contents($this->_file['path'], false);
		}

		return $result;
	}

	private function _draw_footer()
	{
		echo '<br class="clear"/></div>';
	}
}
