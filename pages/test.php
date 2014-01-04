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
html_page_top(plugin_lang_get('remoteimport'));

access_ensure_global_level(config_get('manage_plugin_threshold'));

require_once(__DIR__ . '/../core/MantisConnector.php');
require_once(__DIR__ . '/../core/ConnectionException.php');
/**
 *
 * Class for testing the plugin
 * Some attributes must be changed to get the best result
 *
 * @copyright  GNU
 * @author     Thomas Legendre <thomaslegendre.tl@gmail.com>
 * @link       http://www.mantisbt.org
 * @package    MantisSync
 * @subpackage classes
 *
 */
class MantisSync_Test
{
    /**
     * @var array The result of each assert
     */
    public $result = array();
    /**
     * @var array The methods to test
     */
    public $methods = array();
    /**
     * @var int Total number of test
     */
    public $total = 0;
    /**
     * @var int Total of passed test
     */
    public $passed = 0;
    /**
     * @var int Total of failed test
     */
    public $failed = 0;
    /**
     * @var int Remote id of a bug which contains custom fields
     */
    private $remoteBugId = 17795;
    /**
     * @var int Remote project id
     */
    private $remoteProjectId = 342;


    /**
     * Constructeur qui lance l'ensemble des test et affiche les résultats
     */
    function __construct()
    {
        $methodTested = array();
        $methodTested[] = 'test_update_issue';
        $methodTested[] = 'test_check_file_and_format';
        $methodTested[] = 'test_get_issue';
        $methodTested[] = 'test_get_project_list';
        $methodTested[] = 'test_get_result';
        $methodTested[] = 'test_import_project_issues';
        $methodTested[] = 'test_get_instance';

        foreach ($methodTested as $method) {
            try {
                $this->methods['MantisSync_Test::' . $method] = 0;
                $this->$method();
            } catch (Exception $e) {
                echo "$method throw an exception with message $e->getMessage()";
            }
        }
    }

    /**
     *   Check the format of the configuration file
     */
    function test_check_file_and_format()
    {
        # KO
        $result = MantisConnector::get_instance()->_check_file_format($this->remoteProjectId, null);
        $this->assertNotNull($result, __METHOD__, ' bad conf file');
        $this->assertTrue(isset($result['error']), __METHOD__, ' bad conf file');

        # OK
        $conf = array();
        $result = MantisConnector::get_instance()->_check_file_format($this->remoteProjectId, $conf);
        $this->assertNotNull($result, __METHOD__, ' bonne conf');
        $this->assertFalse(isset($result['error']), __METHOD__, ' good conf');

        # KO
        $exc = null;
        try {
            $conf = array();
            MantisConnector::get_instance()->_check_file_format(3, $conf);
        } catch (ConnectionException $e) {
            $exc = $e;
        }
        $this->assertNotNull($exc, __METHOD__, ' bad project id');

        # KO
        $conf = array('adzdz' => 'daz');
        $exc = MantisConnector::get_instance()->_check_file_format($this->remoteProjectId, $conf);
        $this->assertTrue(isset($exc['error']), __METHOD__, ' wrong file ');

        # KO
        $conf = array('test' => 'daz');
        $exc = MantisConnector::get_instance()->_check_file_format($this->remoteProjectId, $conf);
        $this->assertTrue(isset($exc['error']), __METHOD__, ' wrong file 2');

        # KO
        $conf = array('testdaz' => 'Phase fiche');
        $exc = MantisConnector::get_instance()->_check_file_format($this->remoteProjectId, $conf);
        $this->assertTrue(isset($exc['error']), __METHOD__, ' wrong file 3');

        # OK
        $conf = array('test' => 'Phase fiche');
        $exc = MantisConnector::get_instance()->_check_file_format($this->remoteProjectId, $conf);
        $this->assertTrue($exc, __METHOD__, ' good file 1');

        # OK
        $conf = array('test' => 'Phase fiche', 'tt' => 'tt');
        $exc = MantisConnector::get_instance()->_check_file_format($this->remoteProjectId, $conf);
        $this->assertTrue($exc, __METHOD__, ' good file 2');
    }

    /**
     * Retrieve an issue from remote Mantis with its id
     */
    function test_get_issue()
    {
        # OK
        $exist = MantisConnector::get_instance()->_get_issue($this->remoteBugId);
        $this->assertNotNull($exist, __METHOD__, ' existing bug');
        $this->assertTrue(is_array($exist), __METHOD__, ' existing bug and good type');

        # KO
        $exc = null;
        try {
            MantisConnector::get_instance()->_get_issue(0);
        } catch (ConnectionException $e) {
            $exc = $e;
        }
        $this->assertNotNull($exc, __METHOD__, ' non existing bug exception catched');

    }

    /**
     *  Update an issue with or without the configuration file
     */
    function test_update_issue()
    {
        # OK
        $bd = new BugData();
        $bd->__set('project_id', $this->remoteProjectId);
        $res = MantisConnector::get_instance()->_update_issue($bd, $this->remoteBugId);
        $localImportBug = bug_cache_row($res);
        $remoteImportBug = MantisConnector::get_instance()->_get_issue($this->remoteBugId);

        # Ok
        $this->assertTrue(
            $localImportBug['summary'] == (str_pad(
                    $remoteImportBug['id'],
                    7,
                    '0',
                    STR_PAD_LEFT
                ) . '-_-' . $remoteImportBug['summary']),
            __METHOD__,
            " check title"
        );
        # Ok
        $this->assertTrue(
            $localImportBug['status'] == strval($remoteImportBug['status']['id']),
            __METHOD__,
            " check status"
        );

        # Ok
        $query = "SELECT *" . " FROM " . db_get_table('mantis_bugnote_table') . " WHERE bug_id=" . $res;
        $result = db_query_bound($query);
        $remoteImportBug['notes'] = ($remoteImportBug['notes']) ? $remoteImportBug['notes'] : array();
        $this->assertTrue(db_num_rows($result) == count($remoteImportBug['notes']), __METHOD__, " note");

        # Ok
        $query = "SELECT *" . " FROM " . db_get_table('mantis_bug_file_table') . " WHERE bug_id=" . $res;
        $result = db_query_bound($query);
        $remoteImportBug['attachments'] = ($remoteImportBug['attachments']) ? $remoteImportBug['attachments'] : array();
        $this->assertTrue(db_num_rows($result) == count($remoteImportBug['attachments']), __METHOD__, " file");
    }

    /**
     * Return the only instance of the mantis connector
     * must save password and other informations if there are exact
     * else the data must be given
     */
    function test_get_instance()
    {
        # setUp
        $url = plugin_config_get('url');
        $user = plugin_config_get('username');
        $passwd = plugin_config_get('password');
        plugin_config_set('url', '');
        plugin_config_set('username', '');
        plugin_config_set('password', '');
        MantisConnector::reset();

        # KO
        $exc = null;
        try {
            MantisConnector::get_instance();
        } catch (ConnectionException $e) {
            $exc = $e;
        }
        $this->assertNotNull($exc, __METHOD__, ' Exception catched');

        # KO
        $exc = null;
        try {
            MantisConnector::get_instance($url);
        } catch (ConnectionException $e) {
            $exc = $e;
        }
        $this->assertNotNull($exc, __METHOD__, ' Exception catched');

        # KO
        $instance = MantisConnector::get_instance($url, "test", "test");
        $this->assertFalse($instance instanceof MantisConnector, __METHOD__, " false user & password ");

        # OK
        $instance = MantisConnector::get_instance($url, $user, $passwd);
        $this->assertTrue($instance instanceof MantisConnector, __METHOD__, " good user & password ");

        # OK
        $instance = MantisConnector::get_instance();
        $this->assertTrue($instance != null, __METHOD__, " simple getInstance with data from database");

    }

    /**
     * With get_project_list call api wich normally deliver a list of project
     */
    function test_get_project_list()
    {
        # OK
        $projectList = MantisConnector::get_instance()->get_project_list();
        $this->assertTrue(is_array($projectList), __METHOD__, " too few parameters to change");
    }

    /**
     *  Test of method get_result which call soap function
     */
    function test_get_result()
    {
        # OK
        $rs = MantisConnector::get_instance()->get_result('mc_projects_get_user_accessible');
        $this->assertNotNull($rs, __METHOD__, ' soap call withour required parameter');

        # KO
        $rs = MantisConnector::get_instance()->get_result('mc_issue_exists');
        $this->assertTrue($rs == null, __METHOD__, ' soap call without the required parameters');
    }

    /**
     *  Test of mmethod import_project_issues
     */
    function test_import_project_issues()
    {
        $pageNumber = null;
        $perPage = null;
        $pageCount = null;
        $bugCount = null;
        $filter = null;
        $user = null;
        $sticky = null;

        $localIssues = filter_get_bug_rows($pageNumber, $perPage, $pageCount, $bugCount, $filter, 1, $user, $sticky);
        $before = count($localIssues);

        $res = MantisConnector::get_instance()->import_project_issues($this->remoteProjectId, 1);
        $this->assertNotNull($res, __METHOD__, ' a result from this method');
        $this->assertTrue((isset($res['new']) || isset($res['update'])), __METHOD__, ' some bug updates or creations');

        $localIssues = filter_get_bug_rows($pageNumber, $perPage, $pageCount, $bugCount, $filter, 1, $user, $sticky);
        $after = count($localIssues);

        $this->assertTrue($after == ($res['new'] + $before), __METHOD__, ' check count');
    }

    // -------------------------------------------------------------------------------------------

    /**
     *
     * Condition must be False to pass the test and save test information
     *
     * @param bool   $cond
     * @param string $method
     * @param string $message
     */
    function assertFalse($cond, $method, $message = '')
    {
        $this->methods[$method]++;
        $this->total++;
        if ($cond) {
            $this->result[$method][] = "<span style='color:red'>FAILED : $message</span><br>";
            $this->failed++;
        } else {
            $this->passed++;
            $this->result[$method][] = "<span style='color:green'>PASSED : $message</span><br>";
        }
    }

    /**
     *
     * Condition must not be null to pass the test and save test information
     *
     * @param mixed  $cond
     * @param string $method
     * @param string $message
     */
    function assertNotNull($cond, $method, $message = '')
    {
        $this->methods[$method]++;
        $this->total++;
        if ($cond == null) {
            $this->failed++;
            $this->result[$method][] = "<span style='color:red'>FAILED : $message</span><br>";
        } else {
            $this->passed++;
            $this->result[$method][] = "<span style='color:green'>PASSED : $message</span><br>";
        }
    }

    /**
     *
     * Condition must be True to pass the test and save test information
     *
     * @param bool   $cond
     * @param string $method
     * @param string $message
     */
    function assertTrue($cond, $method, $message = '')
    {
        $this->assertFalse(!$cond, $method, $message);
    }

    /**
     *
     * Condition must be null to pass the test and save test information
     *
     * @param mixed  $cond
     * @param string $method
     * @param string $message
     */
    function assertNull($cond, $method, $message = '')
    {
        $this->assertNotNull(!$cond, $method, $message);
    }

}

?>
<?php

/**
 * Display all tests and their result
 */
$t_this_page = plugin_page('import');
?>
    <div class="center">
        <table style="width:140%">
            <tr>
                <td class="form-title" colspan="2">
                    Unit testing MantisSync
                </td>
            </tr>
            <?php $mc = new MantisSync_Test(); ?>
            <!--  Bilan -->
            <tr class='row-1'>
                <td class=\
                "category\" width=\"20%\">Bilan</td>
                <td>
                    Test total : <?php echo $mc->total ?><br>
                    Test Ok : <?php echo $mc->passed ?><br>
                    Test KO : <?php echo $mc->failed ?><br>
                    Validation :  <?php echo (int)(($mc->passed / $mc->total) * 100) ?> % <br>
                </td>
            </tr>

            <!-- Résumé-->
            <tr class='row-1'>
                <td class=\
                "category\" width=\"20%\">Resume</td>
                <td>
                    <table>
                        <?php
                        foreach ($mc->methods as $meth => $nb) {
                            echo "<tr>";
                            echo "<td style=\"color:" . (($nb) ? 'green' : 'red') . "\">" . $meth . "</td>";
                            echo "<td> " . $nb . "</td>";
                            echo "</tr>";
                        }
                        ?>
                    </table>
                </td>
            </tr>

            <!--  Détails -->
            <tr class='row-1'>
                <td class="category" width="20%">Details</td>
                <td>
                    <table>
                        <?php
                        foreach ($mc->result as $m => $testList) {
                            echo "<tr><td>";
                            echo $m;
                            echo "</td><td>";
                            foreach ($testList as $test) {
                                echo $test;
                            }
                            echo "</td></tr>";
                        }
                        ?>
                    </table>
                </td>
            </tr>
        </table>
    </div>
<?php
html_page_bottom();
