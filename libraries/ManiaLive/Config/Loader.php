<?php
/**
 * ManiaLive - TrackMania dedicated server manager in PHP
 *
 * @copyright   Copyright (c) 2009-2011 NADEO (http://www.nadeo.com)
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 * @version     $Revision$:
 * @author      $Author$:
 * @date        $Date$:
 */

namespace ManiaLive\Config;

class Loader extends \ManiaLib\Application\ConfigLoader
{
	function run()
	{
		$manialiveAliases = array(
			'config' => 'ManiaLive\\Config\\Config',
			'database' => 'ManiaLive\\Database\\Config',
			'wsapi' => 'ManiaLib\\WebServices\\Config',
			'manialive' => 'ManiaLive\\Application\\Config',
			'server' => 'ManiaLive\\DedicatedApi\\Config',
			'threading' => 'ManiaLive\\Threading\\Config'
		);
		
		$this->aliases = array_merge($this->aliases, $manialiveAliases);
		
		parent::run();
	}
}

?>