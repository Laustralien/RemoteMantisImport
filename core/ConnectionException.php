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
 * Class ConnectionException
 * Custom exception to be sure to catch the good one when an error is throw
 *
 * @copyright  GNU
 * @author     Thomas Legendre <thomaslegendre.tl@gmail.com>
 * @link       http://www.mantisbt.org
 * @package    RemoteMantisImport
 * @subpackage classes
 *
 */
class ConnectionException extends Exception{}