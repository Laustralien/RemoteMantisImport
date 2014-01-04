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
 * Page which allow to import issues from a remote Mantis
 *
 * @copyright  GNU
 * @author     Thomas Legendre <thomaslegendre.tl@gmail.com>
 * @link       http://www.mantisbt.org
 * @package    MantisSync
 * @subpackage classes
 *
 */
html_page_top(plugin_lang_get('remoteimport'));

# check access rights
access_ensure_global_level(config_get('manage_project_threshold'));

# includes
require_once(__DIR__ . '/../core/MantisConnector.php');
require_once(__DIR__ . '/../core/ConnectionException.php');

/** @var $g_error array */
$g_error = null;
/** @var $g_result array */
$g_result = null;

/**
 * Remove accentuation
 *
 * @param string $p_str
 *
 * @return string
 */
function remove_not_utf8($p_str)
{
    if (version_compare(phpversion(), '5.4') != -1) {
        return preg_replace(
            "/\\\\u([a-f0-9]{4})/e",
            "iconv('UCS-4LE','UTF-8',pack('V', hexdec('U$1')))",
            json_encode($p_str, JSON_PRETTY_PRINT)
        );
    } else {
        return preg_replace(
            "/\\\\u([a-f0-9]{4})/e",
            "iconv('UCS-4LE','UTF-8',pack('V', hexdec('U$1')))",
            json_encode($p_str)
        );
    }


}

# on submit form
if ($_SERVER['REQUEST_METHOD'] == "POST") {


    # check configuration file
    if (gpc_get_string('type', null) == 'mapper') {
        $t_conf_file = gpc_get_file('file', null);
        if (empty($t_conf_file['name'])) {
            $g_error = plugin_lang_get("confnull");
        }

    }
    # display details
    if (gpc_get_string('details', null)) {

        try {
            # get custom fields
            $c_res = MantisConnector::get_instance()->get_result(
                'mc_project_get_custom_fields',
                array('project_id' => gpc_get_int('remote-project'))
            );

            # get cutom field type
            $c_type = MantisConnector::get_instance()->get_result(
                'mc_enum_custom_field_types',
                array('project_id' => gpc_get_int('remote-project'))
            );

            # in array
            $c_res = json_decode(json_encode($c_res), true);
            $c_type = json_decode(json_encode($c_type), true);

            # construct exemple and help
            $g_exemple = array();
            $g_help = array();
            foreach ($c_res as $t_exe) {
                $g_help[$t_exe['field']['name']] = $c_type[$t_exe['type']]['name'] . "(" . $t_exe['possible_values'] . ")";
            }

            # description of locals fields
            $c_loc = custom_field_get_linked_ids(gpc_get_int('local-project'));
            $g_local = array();
            foreach ($c_loc as $t_id) {
                $t_def = custom_field_get_definition($t_id);
                $g_exemple[$t_def['name']] = '';
                $g_local[$t_def['name']] = $c_type[$t_def['type']]['name'] . "(" . $t_def['possible_values'] . ")";
            }
        } catch (ConnectionException $e) {
            $g_error = $e . '<br>' . plugin_lang_get("connectremote");
        }
    } # save
    elseif (is_null($g_error)) {
        $t_file = gpc_get_file('file');
        $g_result = MantisConnector::get_instance()->import_project_issues(
            gpc_get_int('remote-project'),
            gpc_get_int('local-project'),
            (gpc_get_string('type') == 'mapper') ? gpc_get_file('file') : null
        );
    }

}
?>

    <div class="center">
    <span style="color:red">
            <?php
            if ($g_result) {
                if (isset($g_result['new']) && isset($g_result['update'])) {
                    echo $g_result['new'] . sprintf(plugin_lang_get('update'), $g_result['update']);
                }
                if (isset($g_result['error'])) {
                    echo $g_result['error'];
                }
            }
            ?>
            <?php echo $g_error ?>
        </span>
        <?php try {
            $t_projects = MantisConnector::get_instance()->get_project_list();
            if (count(current_user_get_accessible_projects()) >= 1) {
                if (count($t_projects) >= 1) {
                    ?>
                    <form name="file_upload" method="post" enctype="multipart/form-data"
                          action="<?php echo plugin_page('import') ?>">
                        <table class="width100">
                            <tr>
                                <td class="form-title" colspan="2">
                                    <?php echo plugin_lang_get("import") ?>
                                </td>
                            </tr>

                            <tr class="row-1">
                                <td class="category" width="25%">
                                    <span class="required">*</span>
                                    <?php echo plugin_lang_get("selectremote") ?>
                                </td>
                                <td width="85%">
                                    <select name="remote-project">
                                        <?php

                                        if (is_array($t_projects)) {
                                            foreach ($t_projects as $t_project) {
                                                $t_arr = (array)$t_project;
                                                if (gpc_get_int('remote-project', null) == $t_arr['id']) {
                                                    echo "<option selected value='" . $t_arr['id'] . "' >" . $t_arr['name'] . "</option>";
                                                } else {
                                                    echo "<option value='" . $t_arr['id'] . "' >" . $t_arr['name'] . "</option>";
                                                }
                                            }
                                        }

                                        ?>
                                    </select>
                                    <input type="submit" name="details" class="button" value="Détails"/>
                                </td>
                            </tr>
                            <tr class="row-1">
                                <td class="category" width="25%">
                                    <span class="required">*</span>
                                    <?php echo plugin_lang_get("selectlocal") ?>
                                </td>
                                <td width="85%">
                                    <select name="local-project">
                                        <?php print_project_option_list(null, false) ?>
                                    </select>
                                </td>
                            </tr>

                            <tr class="row-1">
                                <td class="category" width="25%">
                                    <span class="required">*</span>
                                    <?php echo plugin_lang_get("type") ?>
                                </td>
                                <td width="85%">
                                    <input name="type" value="minimum" type="radio" checked>Minimum</input>
                                    <br>
                                    <input name="type" value="mapper" type="radio">Mapper<span
                                        class="required"> <?php echo plugin_lang_get("fichierrequired") ?></span></input>
                                </td>
                            </tr>
                            <tr class="row-1">
                                <td class="category" width="25%">
                                    <?php echo plugin_lang_get("fichier") ?>
                                </td>
                                <td width="85%">
                                    <input name="file" type="file" size="40"/>
                                </td>
                            </tr>

                            <td colspan="2" class="center">
                                <input type="submit" class="button" value="Importer"/>
                            </td>
                        </table>
                    </form>
                    <?php if ($_SERVER['REQUEST_METHOD'] == "POST" && gpc_get_string('details', null)) {
                        if ($g_exemple) {
                            echo '<textarea cols="75" rows="15">';
                            echo "========= " . plugin_lang_get('format') . " ========= \n";
                            echo remove_not_utf8($g_exemple);
                            echo "\n========= " . plugin_lang_get('descremote') . " ========= \n";
                            echo remove_not_utf8($g_help);
                            echo "\n========= " . plugin_lang_get('desclocal') . " ========= \n";
                            echo remove_not_utf8($g_local);
                            echo "</textarea>";
                        } else {
                            echo '<span style = "color:red" > ' . plugin_lang_get('noconfigallowed') . '</span > ';
                        }
                    }
                } else {
                    echo '<span style = "color:red" > ' . plugin_lang_get('noneremote') . '</span > ';
                }
            } else {
                echo '<span style = "color:red" > ' . plugin_lang_get('nonelocal') . '</span > ';
            }
        } catch (ConnectionException $e) {
            echo '<span style = "color:red" > ' . plugin_lang_get('noneremote') . ' </span > ';
        }
        ?>
    </div>

<?php
html_page_bottom();
