<?php
# MantisBT - a php based bugtracking system
# Copyright (C) 2002 - 2013  MantisBT Team - mantisbt-dev@lists.sourceforge.net
# MantisBT is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 2 of the License, or
# (at your option) any later version.
#
# MantisBT is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with MantisBT.  If not, see <http://www.gnu.org/licenses/>.
/**
 *
 * Page which allow to save the configuration of the remote Mantis
 *
 * @copyright  GNU
 * @author     Thomas Legendre <thomaslegendre.tl@gmail.com>
 * @link       http://www.mantisbt.org
 * @package    MantisSync
 * @subpackage classes
 *
 */
html_page_top(plugin_lang_get('remoteimport'));

access_ensure_global_level(config_get('manage_plugin_threshold'));

require_once(__DIR__ . '/../core/MantisConnector.php');
require_once(__DIR__ . '/../core/ConnectionException.php');

/** @var $g_error array */
$g_error = null;
/** @var $t_post_url string */
$t_post_url = null;
/** @var $t_post_username string */
$t_post_username = null;
/** @var $t_post_password string */
$t_post_password = null;

if ('POST' == $_SERVER['REQUEST_METHOD']) {

    # verification existance des champs
    if (!is_null(gpc_get_string('username')) && !is_null(gpc_get_string('url')) && !is_null(gpc_get_string('password'))
    ) {
        $t_post_url = gpc_get_string('url');
        $t_post_username = gpc_get_string('username');
        $t_post_password = gpc_get_string('password');

        $g_error = MantisConnector::get_instance($t_post_url, $t_post_username, $t_post_password);
    } # champs requis non remplis
    else {
        $g_error = plugin_lang_get('required');
    }


} else {
    # récupération depuis la base si existant
    if (plugin_config_get('url')) {
        $t_post_url = plugin_config_get('url');
    } else {
        $t_post_url = '';
    }
    if (plugin_config_get('password')) {
        $t_post_password = plugin_config_get('password');
    } else {
        $t_post_password = '';
    }
    if (plugin_config_get('username')) {
        $t_post_username = plugin_config_get('username');
    } else {
        $t_post_username = '';
    }
}
?>
    <div class="center">
        <span style="color:red">
            <?php if ($g_error instanceof MantisConnector) {
                echo plugin_lang_get('saveconf');
            }?>
            <?php echo $g_error ?>
        </span>

        <form name="configuration" method="post" action="<?php echo plugin_page('config') ?>">
            <table class="width100">
                <tr>
                    <td class="form-title" colspan="2">
                        <?php echo plugin_lang_get('remoteconf') ?>
                    </td>
                </tr>

                <tr class="row-1">
                    <td class="category" width="25%">
                        <span class="required">*</span>
                        <?php echo plugin_lang_get('url') ?>
                    </td>
                    <td width="85%">
                        <input type="text" size="80" name="url"
                               value="<?php echo $t_post_url ?>">
                    </td>
                </tr>

                <tr class="row-1">
                    <td class="category" width="25%">
                        <span class="required">*</span>
                        <?php echo plugin_lang_get('user') ?>
                    </td>
                    <td width="85%">
                        <input type="text" name="username"
                               value="<?php echo $t_post_username ?>">
                    </td>
                </tr>
                <tr class="row-1">
                    <td class="category" width="25%">
                        <span class="required">*</span>
                        <?php echo plugin_lang_get('password') ?>
                    </td>
                    <td width="85%">
                        <input type="password" name="password"
                               value="<?php echo $t_post_password ?>">
                    </td>
                </tr>

                <tr>
                    <td colspan="2" class="center">
                        <input type="submit" class="button" value="<?php echo plugin_lang_get('save') ?>"/>
                    </td>
                </tr>
            </table>
        </form>

    </div>
<?php
html_page_bottom();
