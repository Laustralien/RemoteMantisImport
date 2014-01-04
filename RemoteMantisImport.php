<?php
/**
 * requires MantisPlugin.class.php
 */
require_once(config_get('class_path') . 'MantisPlugin.class.php');

/**
 * Class RemoteMantisImportPlugin
 * Plugin installer
 *
 * @copyright  GNU
 * @author     Thomas Legendre <thomaslegendre.tl@gmail.com>
 * @link       http://www.mantisbt.org
 * @package    RemoteMantisImport
 * @subpackage classes
 */
class RemoteMantisImportPlugin extends MantisPlugin
{

    /**
     *  A method that populates the plugin information and minimum requirements.
     */
    function register()
    {

        $this->name = 'RemoteMantisImport';
        $this->description = plugin_lang_get('description');

        $this->page = 'config';

        $this->version = '1.0';
        $this->requires = array(
            'MantisCore' => '1.2.0',
        );

        $this->author = 'Thomas Legendre';
        $this->contact = 'thomaslegendre.tl@gmail.com';
        $this->url = 'http://laustralien.fr';
    }

    /**
     * Default plugin configuration.
     */
    function hooks()
    {
        $hooks = array(
            'EVENT_MENU_MAIN' => 'import_menu',
        );

        return $hooks;
    }

    function import_menu()
    {
        if (access_has_global_level(MANAGER)) {
            return array('<a href="' . plugin_page('import') . '">' . plugin_lang_get("remoteimport") . '</a>',);
        } else {
            return array();
        }
    }

    /**
     * Default plugin configuration.
     */
    function config()
    {
        return array(
            'username' => '',
            'password' => '',
            'url'      => '',
        );
    }

    /**
     * Install required php soap extension
     * @return bool
     */
    function install()
    {
        $result = extension_loaded("soap");
        if (!$result) {
            error_parameters(plugin_lang_get('error_no_soap'));
            trigger_error(ERROR_PLUGIN_INSTALL_FAILED, ERROR);
        }

        return $result;
    }
}