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

# Separator for the summary text containing id of the remote issue
define('SEPARATOR', '-_-');

/**
 * Class MantisConnector
 *
 * Class which is in charge of getting remote issues from an project id.
 * The configuration must be done before any import.
 *
 * @copyright  GNU
 * @author     Thomas Legendre <thomaslegendre.tl@gmail.com>
 * @link       http://www.mantisbt.org
 * @package    RemoteMantisImport
 * @subpackage classes
 *
 */
class MantisConnector
{
    /**
     * @var string The remote allowed user
     */
    private static $g_user = null;

    /**
     * @var string The remote password for user
     */
    private static $g_password = null;

    /**
     * @var string The remote url
     */
    private static $g_url;

    /**
     * @var string The local instance of the connector
     */
    private static $g_instance;


    /**
     * Private constructor
     *
     * @param string $p_url
     * @param string $p_username
     * @param string $p_password
     */
    private function __construct($p_url, $p_username, $p_password)
    {
        # set local attributes
        self::$g_user = $p_username;
        self::$g_password = $p_password;
        if (substr($p_url, -1) == '/') {
            $p_url = substr($p_url, 0, -1);
        }
        self::$g_url = $p_url;
    }

    /**
     * Singleton design pattern
     *
     * @param string|null $p_url      The url of the remote MantisBT
     * @param string|null $p_username The username of the user allowed to connect to remote MantisBt
     * @param string|null $p_password The password of the remote user
     *
     * @return MantisConnector|array The instance or an array containing errors
     *
     * @throws ConnectionException In case of connexion problem
     */
    public static function get_instance($p_url = null, $p_username = null, $p_password = null)
    {
        # check instance exist
        if (is_null(self::$g_instance)) {

            # test fields empty
            if (is_null($p_url) || is_null($p_username) || is_null($p_password)) {
                # check fields in database
                if (plugin_config_get('url') == null ||
                    plugin_config_get('password') == null ||
                    plugin_config_get('username') == null
                ) {
                    throw new ConnectionException(plugin_lang_get('saveconfiguration'));
                } else {
                    # if fiels are null we use database data
                    self::$g_instance = new MantisConnector(
                        plugin_config_get('url'),
                        plugin_config_get('username'),
                        plugin_config_get('password')
                    );

                    return self::$g_instance;
                }
                # If fields are not empty
            } else {

                # valid url
                $t_filtered_url = filter_var($p_url, FILTER_VALIDATE_URL);
                if (false !== $t_filtered_url) {

                    # delete final '/'
                    if (substr($p_url, -1) == '/') {
                        $p_url = substr($p_url, 0, -1);
                    }

                    # url exist
                    $header = @get_headers($p_url . '/api/soap/mantisconnect.php?wsdl');
                    $authorized = array('302', '200');
                    if (0 < count(array_intersect(array_map('strtolower', explode(' ', $header[0])), $authorized))) {

                        # test login password
                        try {
                            # test connexion
                            /** @var $url string */
                            $mt = new MantisConnector($p_url, $p_username, $p_password);
                            $mt->get_result('mc_projects_get_user_accessible');

                            # save in DB
                            plugin_config_set('url', $p_url);
                            plugin_config_set('password', $p_password);
                            plugin_config_set('username', $p_username);

                            # return instance if no errors
                            self::$g_instance = $mt;

                            return self::$g_instance;

                        } catch (ConnectionException $exc) {
                            return $exc->getMessage() . "<br>" . plugin_lang_get('baduserpassword');
                        }
                        # url does not exist
                    } else {
                        return plugin_lang_get('badurlmantis');
                    }
                    # invalid url
                } else {
                    return plugin_lang_get('badurl');
                }
            }
        }

        return self::$g_instance;
    }


    /**
     * Reset function for the unit test
     */
    public static function reset()
    {
        self::$g_instance = null;
        self::$g_password = null;
        self::$g_user = null;
        self::$g_url = null;
    }

    /**
     * Get project list from remote MantisBT
     * @return array from Soap
     */
    public function get_project_list()
    {
        # get main projects
        $t_result = $this->get_result('mc_projects_get_user_accessible');

        # foreach main get sub projects
        foreach ($t_result as $t_project) {
            $t_arr = (array)$t_project;
            $t_arr_sub = (array)$t_arr['subprojects'];
            $t_array_sub = array();
            foreach ($t_arr_sub as $t_sub) {
                $t_arr = (array)$t_sub;
                $t_arr['name'] = ' » ' . $t_arr['name'];
                $t_array_sub[] = $t_arr;
            }
            $t_result = array_merge($t_result, $t_array_sub);
        }

        return $t_result;
    }

    /**
     * Import all issues from the given project into the given local project.
     *
     *
     * @param int   $p_remote_id   int Remote project ID
     * @param int   $p_local_id    int Local project ID
     * @param array $p_config_file array The Configuration file if needed
     *
     * @return array An array which contains the number of created and updated issues.
     */
    public function import_project_issues($p_remote_id, $p_local_id, $p_config_file = null)
    {
        # prepare return
        $c_result = array();
        $c_array_cnf = array();

        #  check configration file
        if ($p_config_file != null) {
            $content = file_get_contents($p_config_file['tmp_name']);

            if ($content) {
                $c_array_cnf = json_decode($content);

                # check field exist and field format
                $ret = $this->_check_file_format($p_remote_id, $c_array_cnf);

                # in case of error
                if (is_array($ret)) {
                    return $ret;
                }
            } else {
                $c_result['error'] = plugin_lang_get('badconf');

                return $c_result;
            }

        }

        # get local bugs for given id project
        $t_page_number = null;
        $t_per_page = null;
        $t_page_count = null;
        $t_bug_count = null;
        $t_filter = null;
        $t_user = null;
        $t_sticky = null;
        $t_local_issues = filter_get_bug_rows(
            $t_page_number,
            $t_per_page,
            $t_page_count,
            $bug_count,
            $t_filter,
            $p_local_id,
            $t_user,
            $t_sticky
        );

        $t_done = array();
        $g_result['update'] = 0;

        # check update to do
        foreach ($t_local_issues as $t_local_issue) {

            # id bug corresponding
            $t_summary = $t_local_issue->summary;
            $c_id = substr($t_summary, 0, 7);

            # treatment if id ok
            if (is_int((int)$c_id)) {
                $this->_update_issue($t_local_issue, $c_id, $c_array_cnf);
                $g_result['update']++;
            }

            # remove from remote list
            $t_done[] = $c_id;
        }

        # list issues from remote mantis
        $c_remote_issues = json_decode(
            json_encode($this->get_result('mc_project_get_issues', array('project_id' => $p_remote_id))),
            true
        );

        $g_result['new'] = 0;
        # create new bug
        foreach ($c_remote_issues as $t_issue) {
            if (!in_array($t_issue['id'], $t_done)) {
                # create new bug object
                $bug = new BugData();
                # update project id
                $bug->__set('project_id', $p_local_id);
                # get data
                $this->_update_issue($bug, $t_issue['id'], $c_array_cnf);
                $g_result['new']++;
            }
        }

        return $g_result;
    }

    /**
     * Return result from soap call
     *
     * @param string $p_fct_name                                              pr0dGFItlk1
     * @param array  $p_params
     *
     * @throws ConnectionException In case of error from the network
     * @return array A string to encode with json
     */
    function get_result($p_fct_name, $p_params = array())
    {
        # check argues
        if (!is_array($p_params)) {
            $p_params = array();
        }

        # add argues
        $c_args = array_merge(
            array(
                'username' => self::$g_user,
                'password' => self::$g_password
            ),
            $p_params
        );

        # connect and do the SOAP call
        try {
            $t_client = new SoapClient(self::$g_url . '/api/soap/mantisconnect.php?wsdl');
            $g_result = $t_client->__soapCall($p_fct_name, $c_args);
        } catch (SoapFault $e) {
            $g_result = array('error' => $e->getMessage());
        }

        # throw error if needed
        if (is_array($g_result) && isset($g_result['error'])) {
            throw new ConnectionException(plugin_lang_get('connectionerror'));
        }

        # return result
        return $g_result;
    }

    /**
     * Update all data from remote issue into the local one
     * This update include notes and files
     *
     * @param BugData $p_local_bug
     * @param int     $p_remote_id
     * @param null    $p_conf
     *
     * @return null|int The local bug id if there is no error
     */
    public function _update_issue(BugData $p_local_bug, $p_remote_id, $p_conf = null)
    {

        try {
            /** @var array */
            if (!is_int($p_remote_id)){
                return;
            }
            $c_remote_issue = $this->_get_issue($p_remote_id);

            # replace all null values by ''  fore required fields
            array_walk_recursive(
                $c_remote_issue,
                function (& $t_item, $t_key) {
                    $t_not_null = array(
                        'steps_to_reproduce',
                        'additional_information',
                        'os',
                        'os_build',
                        'platform',
                        'version',
                        'build',
                        'fixed_in_version',
                    );
                    if ($t_item === null && in_array($t_key, $t_not_null)) {
                        $t_item = '';
                    }
                }
            );

            # update standard data
            $p_local_bug->__set('reporter_id', $c_remote_issue['reporter']['id']);
            $p_local_bug->__set('summary', str_pad($p_remote_id, 7, '0', STR_PAD_LEFT) . SEPARATOR . $c_remote_issue['summary']);
            $p_local_bug->__set('handler_id', $c_remote_issue['handler']['id']);
            $p_local_bug->__set('priority', $c_remote_issue['priority']['id']);
            $p_local_bug->__set('severity', $c_remote_issue['severity']['id']);
            $p_local_bug->__set('reproducibility', $c_remote_issue['reproducibility']['id']);
            $p_local_bug->__set('status', $c_remote_issue['status']['id']);
            $p_local_bug->__set('resolution', $c_remote_issue['resolution']['id']);
            $p_local_bug->__set('projection', $c_remote_issue['projection']['id']);
            $p_local_bug->__set('category_id', 1); //@todo check category exist
            $p_local_bug->__set('eta', $c_remote_issue['eta']['id']);
            $p_local_bug->__set('os', $c_remote_issue['os']);
            $p_local_bug->__set('os_build', $c_remote_issue['os_build']);
            $p_local_bug->__set('platform', $c_remote_issue['platform']);
            $p_local_bug->__set('version', $c_remote_issue['version']);
            $p_local_bug->__set('build', $c_remote_issue['build']);
            $p_local_bug->__set('fixed_in_version', $c_remote_issue['fixed_in_version']);
            $p_local_bug->__set('view_state', $c_remote_issue['view_state']['id']);
            $p_local_bug->__set('description', $c_remote_issue['description']);
            $p_local_bug->__set('steps_to_reproduce', $c_remote_issue['steps_to_reproduce']);
            $p_local_bug->__set('additional_information', $c_remote_issue['additional_information']);
            $p_local_bug->__set('sponsorship_total', $c_remote_issue['sponsorship_total']);


            # Update the bug entry
            if ($p_local_bug->__get('id')) {
                $p_local_bug->update(false, false);
            } else {
                $id = $p_local_bug->create();
                $p_local_bug->__set('id', $id);
            }

            # NOTES
            # delete old notes
            $t_query = "SELECT *" .
                " FROM " . db_get_table('mantis_bugnote_table') .
                " WHERE bug_id=" . $p_local_bug->__get('id');
            $c_result = db_query_bound($t_query);
            foreach ($c_result as $t_note) {
                bugnote_delete($t_note['id']);
            }

            # add nes notes
            if (is_array($c_remote_issue['notes'])) {
                foreach ($c_remote_issue['notes'] as $note) {
                    bugnote_add($p_local_bug->__get('id'), $note['text'], '0:00', false, BUGNOTE);
                }
            }

            # GFILES
            # delete old files
            $t_query = "SELECT *" .
                " FROM " . db_get_table('mantis_bug_file_table') .
                " WHERE bug_id=" . $p_local_bug->__get('id');
            $t_result = db_query_bound($t_query);
            foreach ($t_result as $t_file) {
                file_delete($t_file['id'], 'bug');
            }

            # Add new files
            if (is_array($c_remote_issue['attachments'])) {

                # each file
                foreach ($c_remote_issue['attachments'] as $t_pj) {
                    # get file content to add in db
                    $url_content = $this->get_result(
                        'mc_issue_attachment_get',
                        array('issue_attachment_id' => $t_pj['id'],)
                    );

                    # cange to good format
                    $t_content = db_prepare_binary_string($url_content);

                    # create query
                    $t_query = 'INSERT INTO ' . db_get_table('mantis_bug_file_table') .
                        '(bug_id,title,description,diskfile,filename,folder,filesize,file_type,date_added,content)' .
                        'VALUES' .
                        "( '" . $p_local_bug->__get('id') . "', " . "'', " . "'', '" . $t_pj['download_url'] . "', '" .
                        $t_pj['filename'] . "' , " . "'', " . $t_pj['size'] . ", '" . $t_pj['content_type'] . "', '" .
                        db_now() . "', " . $t_content . ")";

                    db_query_bound($t_query);

                }
            }

            # if configuration file is given
            if ($p_conf != null) {

                # list remote fields/values
                $remote_custom_field = $c_remote_issue['custom_fields'];

                # fore each locals fields
                foreach ($p_conf as $_local_field => $t_remote_field) {
                    $t_local_field_id = custom_field_get_id_from_name($_local_field);

                    # check value of each concern field from complete list of fields
                    if (is_array($remote_custom_field)) {
                        foreach ($remote_custom_field as $t_remote_value) {
                            if ($t_remote_value['field']['name'] == $t_remote_field) {
                                custom_field_set_value($t_local_field_id, $p_local_bug->__get('id'), $t_remote_value['value']);
                                break;
                            }
                        }
                    }
                }
            }

            return $p_local_bug->__get('id');

        } catch (Exception $e) {
            echo $e;
        }

        return null;
    }

    /**
     * Return an issue in an array
     *
     * @param int $p_id int issue ID
     *
     * @return array from SOAP
     */
    public function _get_issue($p_id)
    {
        return json_decode(
            json_encode($this->get_result('mc_issue_get', array('issue_id' => $p_id))),
            true
        );
    }

    /**
     * Return an error if exist else return true
     *
     * @param int   $p_remote_id  int the remote project ID to check custom fields
     * @param array $p_array_cnf  array The file data
     *
     * @return array|bool in case of error else true
     */
    public function _check_file_format($p_remote_id, $p_array_cnf)
    {
        $g_result = array();

        if (is_null($p_array_cnf)) {
            $g_result['error'] = plugin_lang_get('badconf');

            return $g_result;

        } # vérification de la validité des champs
        else {
            # get custom fields
            $t_res = MantisConnector::get_instance()->get_result(
                'mc_project_get_custom_fields',
                array('project_id' => $p_remote_id)
            );

            # change in array with the name as key
            $t_res = json_decode(json_encode($t_res), true);
            $t_fields_id = array();
            foreach ($t_res as $t_field) {
                $t_fields_id[$t_field['field']['name']] = $t_field;
            }

            # get cutom field type
            $t_type = MantisConnector::get_instance()->get_result(
                'mc_enum_custom_field_types',
                array('project_id' => $p_remote_id)
            );

            # chang in  array  with id as key
            $t_type = json_decode(json_encode($t_type), true);
            $t_remote_field_type_id = array();
            foreach ($t_type as $t) {
                $t_remote_field_type_id[$t['id']] = $t['name'];
            }

            $t_config_var_value = config_get('custom_field_type_enum_string');
            $t_translated_values = lang_get('custom_field_type_enum_string', 'french');
            $t_enum_values = MantisEnum::getValues($t_config_var_value);
            $t_local_field_type_id = array();
            foreach ($t_enum_values as $t_key) {
                $t_translated = MantisEnum::getLocalizedLabel($t_config_var_value, $t_translated_values, $t_key);
                $t_local_field_type_id[$t_key] = $t_translated;
            }

            # check exist for current project
            foreach ($p_array_cnf as $t_local => $t_remote) {

                # try get id
                $t_local_field_id = custom_field_get_id_from_name($t_local);
                if (!$t_local_field_id) {
                    $g_result['error'] = $t_local . plugin_lang_get('fieldlocal');

                    return $g_result;
                } # exist then check format
                else {

                    # check remote exist
                    if (!array_key_exists($t_remote, $t_fields_id)) {
                        $g_result['error'] = $t_remote . plugin_lang_get('fieldremote');

                        return $g_result;
                    } # exist then check format
                    else {
                        # local def
                        $t_def = custom_field_get_definition($t_local_field_id);
                        $t_id = (int)$t_def['type'];


                        # check
                        if ($t_remote_field_type_id[$t_fields_id[$t_remote]['type']] != $t_local_field_type_id[$t_id] ||
                            $t_fields_id[$t_remote]['possible_values'] != $t_def['possible_values'] ||
                            $t_fields_id[$t_remote]['valid_regexp'] != $t_def['valid_regexp']
                        ) {
                            $g_result['error'] = plugin_lang_get('fieldtype');

                            return $g_result;
                        }
                    }
                }
            }
        }

        # no error the return
        return true;

    }


}
