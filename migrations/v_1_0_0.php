<?php
/**
 *
 * Sticky Ad. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2019, toxyy, https://github.com/toxyy
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace toxyy\stickyad\migrations;

class v_1_0_0 extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['stickyad']);
	}

	public function update_data()
	{
		return array(
			// Add configs
			array('config.add', array('stickyad', '1')),
		);
	}
}
